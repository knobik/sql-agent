<?php

namespace Knobik\SqlAgent\Tests;

use Knobik\SqlAgent\Facades\SqlAgent;
use Knobik\SqlAgent\SqlAgentServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Prism\Prism\PrismServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        $providers = [
            PrismServiceProvider::class,
            SqlAgentServiceProvider::class,
        ];

        // Add Livewire provider if available
        if (class_exists(LivewireServiceProvider::class)) {
            $providers[] = LivewireServiceProvider::class;
        }

        return $providers;
    }

    protected function getPackageAliases($app): array
    {
        return [
            'SqlAgent' => SqlAgent::class,
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
    }
}
