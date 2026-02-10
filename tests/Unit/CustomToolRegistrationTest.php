<?php

use Knobik\SqlAgent\Agent\ToolRegistry;
use Prism\Prism\Tool;

describe('Custom Tool Registration', function () {
    it('registers custom tools from config', function () {
        config()->set('sql-agent.agent.tools', [FakeCustomTool::class]);

        $registry = app(ToolRegistry::class);

        expect($registry->has('fake_custom'))->toBeTrue();
        expect($registry->get('fake_custom'))->toBeInstanceOf(Tool::class);
    });

    it('custom tools appear alongside built-in tools', function () {
        config()->set('sql-agent.agent.tools', [FakeCustomTool::class]);

        $registry = app(ToolRegistry::class);

        // Built-in tools should still be present
        expect($registry->has('run_sql'))->toBeTrue();
        expect($registry->has('introspect_schema'))->toBeTrue();
        expect($registry->has('save_learning'))->toBeTrue();
        expect($registry->has('save_validated_query'))->toBeTrue();
        expect($registry->has('search_knowledge'))->toBeTrue();

        // Custom tool should also be present
        expect($registry->has('fake_custom'))->toBeTrue();
        expect($registry->count())->toBe(6);
    });

    it('resolves custom tools with constructor dependencies from the container', function () {
        $this->app->bind(FakeDependency::class, fn () => new FakeDependency('injected'));

        config()->set('sql-agent.agent.tools', [FakeCustomToolWithDependency::class]);

        $registry = app(ToolRegistry::class);

        expect($registry->has('fake_with_dep'))->toBeTrue();
    });

    it('throws exception for non-existent tool class', function () {
        config()->set('sql-agent.agent.tools', ['App\\NonExistent\\ToolClass']);

        app(ToolRegistry::class);
    })->throws(InvalidArgumentException::class, 'Custom tool class [App\\NonExistent\\ToolClass] does not exist.');

    it('throws exception for tool class that does not extend Tool', function () {
        config()->set('sql-agent.agent.tools', [NotATool::class]);

        app(ToolRegistry::class);
    })->throws(InvalidArgumentException::class, 'must extend');
});

// Test fixtures

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

class FakeDependency
{
    public function __construct(public string $value) {}
}

class FakeCustomToolWithDependency extends Tool
{
    public function __construct(FakeDependency $dep)
    {
        $this
            ->as('fake_with_dep')
            ->for("A fake tool with dependency: {$dep->value}")
            ->using(fn () => 'fake');
    }
}

class NotATool
{
    // Intentionally not extending Tool
}
