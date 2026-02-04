<?php

namespace Knobik\SqlAgent\Tests;

use Knobik\SqlAgent\SqlAgentServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        $providers = [
            SqlAgentServiceProvider::class,
        ];

        // Add Livewire provider if available
        if (class_exists(\Livewire\LivewireServiceProvider::class)) {
            $providers[] = \Livewire\LivewireServiceProvider::class;
        }

        return $providers;
    }

    protected function getPackageAliases($app): array
    {
        return [
            'SqlAgent' => \Knobik\SqlAgent\Facades\SqlAgent::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // Set app key for Livewire tests
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Set dummy API keys to prevent service provider failures during tests
        $app['config']->set('sql-agent.llm.drivers.openai.api_key', 'test-openai-key');
        $app['config']->set('sql-agent.llm.drivers.anthropic.api_key', 'test-anthropic-key');
    }
}
