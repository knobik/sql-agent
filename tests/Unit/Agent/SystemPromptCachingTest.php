<?php

use Illuminate\Support\Facades\File;
use Knobik\SqlAgent\Agent\AgentLoopContext;
use Knobik\SqlAgent\Agent\PromptRenderer;
use Knobik\SqlAgent\Data\Context;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

function makeContext(bool $dynamicSchema = false): Context
{
    return new Context(
        semanticModel: '## Table: users',
        businessRules: 'Active means deleted_at is null.',
        queryPatterns: collect(),
        learnings: collect([
            ['title' => 'users.status is a string', 'description' => "Compare to 'active'."],
        ]),
        customKnowledge: collect(),
        semanticModelIsDynamic: $dynamicSchema,
    );
}

test('the static context holds the schema and business rules', function () {
    $static = makeContext()->toStaticPromptString();

    expect($static)->toContain('DATABASE SCHEMA')
        ->and($static)->toContain('BUSINESS RULES & DEFINITIONS')
        ->and($static)->not->toContain('RELEVANT LEARNINGS');
});

test('the dynamic context holds only the question-dependent layers', function () {
    $dynamic = makeContext()->toDynamicPromptString();

    expect($dynamic)->toContain('RELEVANT LEARNINGS')
        ->and($dynamic)->not->toContain('DATABASE SCHEMA')
        ->and($dynamic)->not->toContain('BUSINESS RULES');
});

test('a retrieved schema moves to the dynamic context', function () {
    $context = makeContext(dynamicSchema: true);

    expect($context->toStaticPromptString())->not->toContain('DATABASE SCHEMA')
        ->and($context->toDynamicPromptString())->toContain('DATABASE SCHEMA');
});

test('toPromptString still returns every layer', function () {
    $prompt = makeContext()->toPromptString();

    expect($prompt)->toContain('DATABASE SCHEMA')
        ->and($prompt)->toContain('BUSINESS RULES & DEFINITIONS')
        ->and($prompt)->toContain('RELEVANT LEARNINGS');
});

test('the system prompt carries no timestamp so it can be cached', function () {
    $prompt = app(PromptRenderer::class)->renderSystem('# DATABASE SCHEMA', [
        'tools' => [],
        'connections' => [],
    ]);

    expect($prompt)->not->toContain('Current date and time');
});

test('the current time is rendered into the dynamic block', function () {
    $rendered = app(PromptRenderer::class)->renderContext('# RELEVANT LEARNINGS');

    expect($rendered)->toContain('Current date and time')
        ->and($rendered)->toContain('RELEVANT LEARNINGS');
});

test('the dynamic block renders without any retrieved context', function () {
    $rendered = app(PromptRenderer::class)->renderContext('');

    expect($rendered)->toContain('Current date and time')
        ->and($rendered)->not->toContain('prepared based on');
});

test('cache control is attached to the static block when caching is enabled', function () {
    config()->set('sql-agent.llm.cache_system_prompt', true);
    config()->set('sql-agent.llm.cache_type', 'ephemeral');
    config()->set('sql-agent.llm.cache_ttl', '1h');

    $prompts = buildSystemPrompts('static prefix', 'dynamic suffix');

    expect($prompts)->toHaveCount(2)
        ->and($prompts[0]->providerOptions('cacheType'))->toBe('ephemeral')
        ->and($prompts[0]->providerOptions('cacheTtl'))->toBe('1h')
        ->and($prompts[1]->providerOptions('cacheType'))->toBeNull();
});

test('no cache control is attached when caching is disabled', function () {
    config()->set('sql-agent.llm.cache_system_prompt', false);

    $prompts = buildSystemPrompts('static prefix', 'dynamic suffix');

    expect($prompts[0]->providerOptions('cacheType'))->toBeNull();
});

test('the ttl is omitted when not configured', function () {
    config()->set('sql-agent.llm.cache_system_prompt', true);
    config()->set('sql-agent.llm.cache_type', 'ephemeral');
    config()->set('sql-agent.llm.cache_ttl', null);

    $prompts = buildSystemPrompts('static prefix', 'dynamic suffix');

    expect($prompts[0]->providerOptions('cacheType'))->toBe('ephemeral')
        ->and($prompts[0]->providerOptions('cacheTtl'))->toBeNull();
});

test('the loop context falls back to a single system block', function () {
    $loop = new AgentLoopContext('the whole prompt', [], [], 5);

    $prompts = $loop->systemPrompts();

    expect($prompts)->toHaveCount(1)
        ->and($prompts[0])->toBeInstanceOf(SystemMessage::class)
        ->and($prompts[0]->content)->toBe('the whole prompt');
});

test('a prompt published to the documented path overrides the package default', function () {
    // The sql-agent-prompts tag publishes here, but the namespace is
    // "sql-agent-prompts" — Laravel would otherwise only look in
    // resources/views/vendor/sql-agent-prompts.
    $published = resource_path('views/vendor/sql-agent/prompts');

    File::ensureDirectoryExists($published);
    File::put($published.'/system.blade.php', 'PUBLISHED PROMPT');

    // The namespace is registered at boot, so the app has to come back up with
    // the published directory already in place.
    $this->refreshApplication();

    try {
        $rendered = app(PromptRenderer::class)->renderSystem('', ['tools' => [], 'connections' => []]);

        expect(trim($rendered))->toBe('PUBLISHED PROMPT');
    } finally {
        File::deleteDirectory($published);
    }
});

test('the package default is used when nothing is published', function () {
    $rendered = app(PromptRenderer::class)->renderSystem('', ['tools' => [], 'connections' => []]);

    expect($rendered)->toContain('self-learning data agent');
});

test('the search-first instruction follows its config flag', function () {
    config()->set('sql-agent.agent.search_first', true);
    $enabled = app(PromptRenderer::class)->renderSystem('', ['tools' => [], 'connections' => []]);

    config()->set('sql-agent.agent.search_first', false);
    $disabled = app(PromptRenderer::class)->renderSystem('', ['tools' => [], 'connections' => []]);

    expect($enabled)->toContain('ALWAYS start with `search_knowledge`')
        ->and($disabled)->not->toContain('ALWAYS start with `search_knowledge`')
        ->and($disabled)->toContain('Call `search_knowledge` only when');
});

/**
 * Mirrors SqlAgent::buildSystemPrompts(), which is protected.
 *
 * @return SystemMessage[]
 */
function buildSystemPrompts(string $static, string $dynamic): array
{
    $agent = new ReflectionClass(\Knobik\SqlAgent\Agent\SqlAgent::class);
    $method = $agent->getMethod('buildSystemPrompts');

    return $method->invoke(app(\Knobik\SqlAgent\Agent\SqlAgent::class), $static, $dynamic);
}
