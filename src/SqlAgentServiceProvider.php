<?php

declare(strict_types=1);

namespace Knobik\SqlAgent;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Knobik\SqlAgent\Agent\SqlAgent;
use Knobik\SqlAgent\Agent\ToolRegistry;
use Knobik\SqlAgent\Contracts\Agent;
use Knobik\SqlAgent\Contracts\SearchDriver;
use Knobik\SqlAgent\Embeddings\EmbeddingObserver;
use Knobik\SqlAgent\Events\SqlErrorOccurred;
use Knobik\SqlAgent\Listeners\AutoLearnFromError;
use Knobik\SqlAgent\Livewire\ChatComponent;
use Knobik\SqlAgent\Livewire\ConversationList;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Search\SearchManager;
use Knobik\SqlAgent\Services\SchemaIntrospector;
use Knobik\SqlAgent\Tools\IntrospectSchemaTool;
use Knobik\SqlAgent\Tools\RunSqlTool;
use Knobik\SqlAgent\Tools\SaveLearningTool;
use Knobik\SqlAgent\Tools\SaveQueryTool;
use Knobik\SqlAgent\Tools\SearchKnowledgeTool;
use Prism\Prism\Tool;

class SqlAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sql-agent.php', 'sql-agent');

        // Search Manager — singleton because it's a Laravel Manager that caches driver instances
        $this->app->singleton(SearchManager::class, fn ($app) => new SearchManager($app));

        // Bind SearchDriver interface to manager's default driver
        $this->app->bind(SearchDriver::class, fn ($app) => $app->make(SearchManager::class)->driver());

        // Tool Registry — singleton because tools are registered at boot and shared across requests
        $this->app->singleton(ToolRegistry::class, function ($app) {
            $registry = new ToolRegistry;

            // Register built-in tools
            $registry->registerMany([
                new RunSqlTool,
                new IntrospectSchemaTool($app->make(SchemaIntrospector::class)),
                new SearchKnowledgeTool($app->make(SearchManager::class)),
            ]);

            if (config('sql-agent.learning.enabled')) {
                $registry->registerMany([
                    new SaveLearningTool,
                    new SaveQueryTool,
                ]);
            }

            // Register custom tools from config
            foreach (config('sql-agent.agent.tools') as $toolClass) {
                if (! class_exists($toolClass)) {
                    throw new \InvalidArgumentException("Custom tool class [{$toolClass}] does not exist.");
                }

                $tool = $app->make($toolClass);

                if (! $tool instanceof Tool) {
                    throw new \InvalidArgumentException("Custom tool class [{$toolClass}] must extend ".Tool::class.'.');
                }

                $registry->register($tool);
            }

            return $registry;
        });

        // SQL Agent — scoped so per-request state (lastSql, iterations, etc.) is fresh each request
        $this->app->scoped(SqlAgent::class);

        // Bind Agent interface to SqlAgent
        $this->app->bind(Agent::class, SqlAgent::class);

        // Alias for facade accessor
        $this->app->alias(Agent::class, 'sql-agent');
    }

    public function boot(): void
    {
        // Register event listeners
        Event::listen(SqlErrorOccurred::class, AutoLearnFromError::class);

        // Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Views (includes prompts)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sql-agent');
        $this->loadViewsFrom(__DIR__.'/../resources/prompts', 'sql-agent-prompts');

        // Register embedding observers when pgvector driver is active
        $this->registerEmbeddingObservers();

        // Register Livewire components if Livewire is available and UI is enabled
        $this->registerLivewireComponents();

        // Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\InstallCommand::class,
                Console\Commands\LoadKnowledgeCommand::class,
                Console\Commands\RunEvalsCommand::class,
                Console\Commands\ExportLearningsCommand::class,
                Console\Commands\ImportLearningsCommand::class,
                Console\Commands\PruneLearningsCommand::class,
                Console\Commands\PurgeCommand::class,
                Console\Commands\GenerateEmbeddingsCommand::class,
            ]);

            // Publishables
            $this->publishes([
                __DIR__.'/../config/sql-agent.php' => config_path('sql-agent.php'),
            ], 'sql-agent-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'sql-agent-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/sql-agent'),
            ], 'sql-agent-views');

            $this->publishes([
                __DIR__.'/../resources/sql-agent/knowledge' => resource_path('sql-agent/knowledge'),
            ], 'sql-agent-knowledge');

            $this->publishes([
                __DIR__.'/../resources/prompts' => resource_path('views/vendor/sql-agent/prompts'),
            ], 'sql-agent-prompts');
        }
    }

    protected function registerEmbeddingObservers(): void
    {
        $driver = config('sql-agent.search.default');

        if ($driver !== 'pgvector') {
            return;
        }

        if (! config('sql-agent.search.drivers.pgvector.connection')) {
            return;
        }

        QueryPattern::observe(EmbeddingObserver::class);
        Learning::observe(EmbeddingObserver::class);
    }

    protected function registerLivewireComponents(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            return;
        }

        if (! config('sql-agent.ui.enabled')) {
            return;
        }

        \Livewire\Livewire::component('sql-agent-chat', ChatComponent::class);
        \Livewire\Livewire::component('sql-agent-conversation-list', ConversationList::class);
    }
}
