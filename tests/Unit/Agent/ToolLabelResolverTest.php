<?php

use Knobik\SqlAgent\Agent\ToolLabelResolver;
use Knobik\SqlAgent\Llm\StreamChunk;

beforeEach(function () {
    $this->resolver = new ToolLabelResolver;
});

describe('getLabel', function () {
    it('returns label for run_sql', function () {
        expect($this->resolver->getLabel('run_sql'))->toBe('Running SQL query');
    });

    it('returns label for introspect_schema', function () {
        expect($this->resolver->getLabel('introspect_schema'))->toBe('Inspecting schema');
    });

    it('returns label for search_knowledge', function () {
        expect($this->resolver->getLabel('search_knowledge'))->toBe('Searching knowledge base');
    });

    it('returns label for save_learning', function () {
        expect($this->resolver->getLabel('save_learning'))->toBe('Saving learning');
    });

    it('returns label for save_validated_query', function () {
        expect($this->resolver->getLabel('save_validated_query'))->toBe('Saving query pattern');
    });

    it('returns tool name for unknown tools', function () {
        expect($this->resolver->getLabel('custom_tool'))->toBe('custom_tool');
    });
});

describe('getType', function () {
    it('returns sql type for run_sql', function () {
        expect($this->resolver->getType('run_sql'))->toBe('sql');
    });

    it('returns schema type for introspect_schema', function () {
        expect($this->resolver->getType('introspect_schema'))->toBe('schema');
    });

    it('returns search type for search_knowledge', function () {
        expect($this->resolver->getType('search_knowledge'))->toBe('search');
    });

    it('returns save type for save_learning', function () {
        expect($this->resolver->getType('save_learning'))->toBe('save');
    });

    it('returns default type for unknown tools', function () {
        expect($this->resolver->getType('unknown'))->toBe('default');
    });
});

describe('buildStreamChunkFromPrism', function () {
    it('builds chunk for schema tool', function () {
        $chunk = $this->resolver->buildStreamChunkFromPrism('introspect_schema', ['tables' => ['users']]);

        expect($chunk)->toBeInstanceOf(StreamChunk::class);
        expect($chunk->content)->toContain('data-type="schema"');
        expect($chunk->content)->toContain('Inspecting schema');
    });

    it('builds chunk for sql tool with data-sql attribute', function () {
        $chunk = $this->resolver->buildStreamChunkFromPrism('run_sql', ['sql' => 'SELECT * FROM users']);

        expect($chunk->content)->toContain('data-type="sql"');
        expect($chunk->content)->toContain('data-sql="SELECT * FROM users"');
        expect($chunk->content)->toContain('Running SQL query');
    });

    it('escapes html in sql data attribute', function () {
        $chunk = $this->resolver->buildStreamChunkFromPrism('run_sql', ['sql' => 'SELECT * FROM users WHERE name = "test"']);

        expect($chunk->content)->toContain('&quot;test&quot;');
    });

    it('handles sql in query key', function () {
        $chunk = $this->resolver->buildStreamChunkFromPrism('run_sql', ['query' => 'SELECT 1']);

        expect($chunk->content)->toContain('data-sql="SELECT 1"');
    });

    it('appends connection name for run_sql when present', function () {
        $chunk = $this->resolver->buildStreamChunkFromPrism('run_sql', ['sql' => 'SELECT 1', 'connection' => 'sales']);

        expect($chunk->content)->toContain('Running SQL query on sales');
        expect($chunk->content)->toContain('data-sql="SELECT 1"');
    });

    it('appends connection name for introspect_schema when present', function () {
        $chunk = $this->resolver->buildStreamChunkFromPrism('introspect_schema', ['connection' => 'analytics']);

        expect($chunk->content)->toContain('Inspecting schema on analytics');
    });

    it('does not append connection for other tools', function () {
        $chunk = $this->resolver->buildStreamChunkFromPrism('search_knowledge', ['query' => 'test', 'connection' => 'sales']);

        expect($chunk->content)->not->toContain('on sales');
    });

    it('does not append connection when not present', function () {
        $chunk = $this->resolver->buildStreamChunkFromPrism('run_sql', ['sql' => 'SELECT 1']);

        expect($chunk->content)->not->toContain(' on ');
    });
});
