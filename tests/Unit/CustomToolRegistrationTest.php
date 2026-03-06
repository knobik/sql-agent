<?php

use Knobik\SqlAgent\Agent\ToolRegistry;
use Prism\Prism\Tool;

describe('Custom Tool Registration', function () {
    it('registers custom tools from config', function () {
        $tools = array_merge(config('sql-agent.agent.tools'), [FakeCustomTool::class]);
        config()->set('sql-agent.agent.tools', $tools);

        $registry = app(ToolRegistry::class);

        expect($registry->has('fake_custom'))->toBeTrue();
        expect($registry->get('fake_custom'))->toBeInstanceOf(Tool::class);
    });

    it('custom tools appear alongside built-in tools', function () {
        $tools = array_merge(config('sql-agent.agent.tools'), [FakeCustomTool::class]);
        config()->set('sql-agent.agent.tools', $tools);

        $registry = app(ToolRegistry::class);

        // Built-in tools should still be present
        expect($registry->has('run_sql'))->toBeTrue();
        expect($registry->has('introspect_schema'))->toBeTrue();
        expect($registry->has('save_learning'))->toBeTrue();
        expect($registry->has('save_validated_query'))->toBeTrue();
        expect($registry->has('search_knowledge'))->toBeTrue();
        expect($registry->has('ask_user'))->toBeTrue();

        // Custom tool should also be present
        expect($registry->has('fake_custom'))->toBeTrue();
        expect($registry->all())->toHaveCount(7);
    });

    it('resolves custom tools with constructor dependencies from the container', function () {
        $this->app->bind(FakeDependency::class, fn () => new FakeDependency('injected'));

        $tools = array_merge(config('sql-agent.agent.tools'), [FakeCustomToolWithDependency::class]);
        config()->set('sql-agent.agent.tools', $tools);

        $registry = app(ToolRegistry::class);

        expect($registry->has('fake_with_dep'))->toBeTrue();
    });

    it('throws exception for non-existent tool class', function () {
        config()->set('sql-agent.agent.tools', ['App\\NonExistent\\ToolClass']);

        app(ToolRegistry::class);
    })->throws(InvalidArgumentException::class, 'Tool class [App\\NonExistent\\ToolClass] does not exist.');

    it('throws exception for tool class that does not extend Tool', function () {
        config()->set('sql-agent.agent.tools', [NotATool::class]);

        app(ToolRegistry::class);
    })->throws(InvalidArgumentException::class, 'must extend');

    it('allows disabling built-in tools by removing from config', function () {
        // Remove AskUserTool from the tools array
        $tools = array_filter(
            config('sql-agent.agent.tools'),
            fn ($t) => $t !== \Knobik\SqlAgent\Tools\AskUserTool::class,
        );
        config()->set('sql-agent.agent.tools', $tools);

        $registry = app(ToolRegistry::class);

        expect($registry->has('ask_user'))->toBeFalse();
        expect($registry->has('run_sql'))->toBeTrue();
    });
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
