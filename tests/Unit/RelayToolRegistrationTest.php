<?php

// Load the fake Relay facade (prism-php/relay is not a dev dependency)
require_once __DIR__.'/../Fixtures/FakeRelayFacade.php';

use Knobik\SqlAgent\Agent\ToolRegistry;
use Prism\Prism\Tool;
use Prism\Relay\Facades\Relay;

beforeEach(function () {
    app()->forgetInstance(ToolRegistry::class);
});

describe('Relay MCP Tool Registration', function () {
    it('registers relay tools when configured', function () {
        $fakeTool = (new Tool)
            ->as('mcp_weather')
            ->for('Get weather from MCP server')
            ->using(fn () => 'sunny');

        $mock = Mockery::mock();
        $mock->shouldReceive('tools')
            ->with('weather-server')
            ->once()
            ->andReturn([$fakeTool]);
        Relay::swap($mock);

        config()->set('sql-agent.agent.relay', ['weather-server']);

        $registry = app(ToolRegistry::class);

        expect($registry->has('mcp_weather'))->toBeTrue();
        expect($registry->get('mcp_weather'))->toBeInstanceOf(Tool::class);
    });

    it('relay tools appear alongside built-in and custom tools', function () {
        $relayTool = (new Tool)
            ->as('mcp_calculator')
            ->for('Calculator from MCP server')
            ->using(fn () => '42');

        $mock = Mockery::mock();
        $mock->shouldReceive('tools')
            ->with('calc-server')
            ->once()
            ->andReturn([$relayTool]);
        Relay::swap($mock);

        config()->set('sql-agent.agent.tools', [FakeCustomTool::class]);
        config()->set('sql-agent.agent.relay', ['calc-server']);

        $registry = app(ToolRegistry::class);

        // Built-in (5) + custom (1) + relay (1) = 7
        expect($registry->count())->toBe(7);
        expect($registry->has('run_sql'))->toBeTrue();
        expect($registry->has('fake_custom'))->toBeTrue();
        expect($registry->has('mcp_calculator'))->toBeTrue();
    });

    it('registers tools from multiple relay servers', function () {
        $weatherTool = (new Tool)
            ->as('mcp_weather')
            ->for('Weather tool')
            ->using(fn () => 'sunny');

        $calcTool = (new Tool)
            ->as('mcp_calculator')
            ->for('Calculator tool')
            ->using(fn () => '42');

        $mock = Mockery::mock();
        $mock->shouldReceive('tools')
            ->with('weather-server')
            ->once()
            ->andReturn([$weatherTool]);
        $mock->shouldReceive('tools')
            ->with('calc-server')
            ->once()
            ->andReturn([$calcTool]);
        Relay::swap($mock);

        config()->set('sql-agent.agent.relay', ['weather-server', 'calc-server']);

        $registry = app(ToolRegistry::class);

        expect($registry->has('mcp_weather'))->toBeTrue();
        expect($registry->has('mcp_calculator'))->toBeTrue();
    });

    it('skipped when relay config is empty', function () {
        $mock = Mockery::mock();
        $mock->shouldReceive('tools')->never();
        Relay::swap($mock);

        config()->set('sql-agent.agent.relay', []);

        $registry = app(ToolRegistry::class);

        // Only built-in tools
        expect($registry->count())->toBe(5);
    });
});

// Fixture reused from CustomToolRegistrationTest — defined if not already loaded
if (! class_exists(FakeCustomTool::class)) {
    class FakeCustomTool extends Tool
    {
        public function __construct()
        {
            $this
                ->as('fake_custom')
                ->for('A fake custom tool for testing')
                ->using(fn () => 'fake');
        }
    }
}
