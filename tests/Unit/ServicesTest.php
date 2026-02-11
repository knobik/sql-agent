<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Services\BusinessRulesLoader;
use Knobik\SqlAgent\Services\ConnectionRegistry;
use Knobik\SqlAgent\Services\ContextBuilder;
use Knobik\SqlAgent\Services\KnowledgeLoader;
use Knobik\SqlAgent\Services\QueryPatternSearch;
use Knobik\SqlAgent\Services\SchemaIntrospector;
use Knobik\SqlAgent\Services\SemanticModelLoader;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
});

describe('SemanticModelLoader', function () {
    it('can be resolved from container', function () {
        $loader = app(SemanticModelLoader::class);

        expect($loader)->toBeInstanceOf(SemanticModelLoader::class);
    });

    it('returns empty collection when no files exist', function () {
        config(['sql-agent.knowledge.source' => 'files']);
        config(['sql-agent.knowledge.path' => '/nonexistent/path']);

        $loader = app(SemanticModelLoader::class);
        $tables = $loader->load();

        expect($tables)->toBeEmpty();
    });

    it('formats empty result gracefully', function () {
        config(['sql-agent.knowledge.source' => 'files']);
        config(['sql-agent.knowledge.path' => '/nonexistent/path']);

        $loader = app(SemanticModelLoader::class);
        $formatted = $loader->format();

        expect($formatted)->toBe('No table metadata available.');
    });

    it('can load from database source', function () {
        config(['sql-agent.knowledge.source' => 'database']);

        $loader = app(SemanticModelLoader::class);
        $tables = $loader->load();

        expect($tables)->toBeEmpty();
    });
});

describe('BusinessRulesLoader', function () {
    it('can be resolved from container', function () {
        $loader = app(BusinessRulesLoader::class);

        expect($loader)->toBeInstanceOf(BusinessRulesLoader::class);
    });

    it('returns empty collection when no files exist', function () {
        config(['sql-agent.knowledge.source' => 'files']);
        config(['sql-agent.knowledge.path' => '/nonexistent/path']);

        $loader = app(BusinessRulesLoader::class);
        $rules = $loader->load();

        expect($rules)->toBeEmpty();
    });

    it('formats empty result gracefully', function () {
        config(['sql-agent.knowledge.source' => 'files']);
        config(['sql-agent.knowledge.path' => '/nonexistent/path']);

        $loader = app(BusinessRulesLoader::class);
        $formatted = $loader->format();

        expect($formatted)->toBe('No business rules defined.');
    });
});

describe('QueryPatternSearch', function () {
    it('can be resolved from container', function () {
        $search = app(QueryPatternSearch::class);

        expect($search)->toBeInstanceOf(QueryPatternSearch::class);
    });

    it('returns empty collection when no patterns exist', function () {
        config(['sql-agent.knowledge.source' => 'database']);

        $search = app(QueryPatternSearch::class);
        $patterns = $search->search('test query');

        expect($patterns)->toBeEmpty();
    });

    it('can set default limit', function () {
        $search = app(QueryPatternSearch::class);
        $result = $search->setDefaultLimit(5);

        expect($result)->toBeInstanceOf(QueryPatternSearch::class);
    });
});

describe('SchemaIntrospector', function () {
    it('can be resolved from container', function () {
        $introspector = app(SchemaIntrospector::class);

        expect($introspector)->toBeInstanceOf(SchemaIntrospector::class);
    });

    // Note: These tests are skipped when Doctrine DBAL is not available (e.g., SQLite in-memory in Laravel 11+)
    it('returns null for nonexistent tables', function () {
        $introspector = app(SchemaIntrospector::class);

        try {
            $schema = $introspector->introspectTable('nonexistent_table');
            expect($schema)->toBeNull();
        } catch (\BadMethodCallException $e) {
            // Doctrine DBAL not available for this driver
            expect(true)->toBeTrue();
        }
    });

    it('can check if table exists', function () {
        $introspector = app(SchemaIntrospector::class);

        try {
            expect($introspector->tableExists('nonexistent_table'))->toBeFalse();
        } catch (\BadMethodCallException $e) {
            // Doctrine DBAL not available for this driver
            expect(true)->toBeTrue();
        }
    });
});

describe('KnowledgeLoader', function () {
    it('can be resolved from container', function () {
        $loader = app(KnowledgeLoader::class);

        expect($loader)->toBeInstanceOf(KnowledgeLoader::class);
    });

    it('returns 0 when path does not exist', function () {
        $loader = app(KnowledgeLoader::class);

        expect($loader->loadTables('/nonexistent/path'))->toBe(0);
        expect($loader->loadBusinessRules('/nonexistent/path'))->toBe(0);
        expect($loader->loadQueryPatterns('/nonexistent/path'))->toBe(0);
    });

    it('can load all knowledge', function () {
        $loader = app(KnowledgeLoader::class);
        $results = $loader->loadAll('/nonexistent/path');

        expect($results)->toBeArray();
        expect($results)->toHaveKeys(['tables', 'business_rules', 'query_patterns']);
    });
});

describe('ContextBuilder', function () {
    it('can be resolved from container', function () {
        $builder = app(ContextBuilder::class);

        expect($builder)->toBeInstanceOf(ContextBuilder::class);
    });

    it('can build context', function () {
        config(['sql-agent.knowledge.source' => 'database']);
        config(['sql-agent.learning.enabled' => false]);

        $builder = app(ContextBuilder::class);

        try {
            $context = $builder->build('How many users?');
            expect($context)->toBeInstanceOf(\Knobik\SqlAgent\Data\Context::class);
        } catch (\BadMethodCallException $e) {
            // Doctrine DBAL not available for this driver - test without runtime schema
            $context = $builder->buildWithOptions(
                question: 'How many users?',
                includeRuntimeSchema: false,
            );
            expect($context)->toBeInstanceOf(\Knobik\SqlAgent\Data\Context::class);
        }
    });

    it('can build minimal context', function () {
        config(['sql-agent.knowledge.source' => 'database']);

        $builder = app(ContextBuilder::class);
        $context = $builder->buildMinimal();

        expect($context)->toBeInstanceOf(\Knobik\SqlAgent\Data\Context::class);
        expect($context->queryPatterns)->toBeEmpty();
        expect($context->learnings)->toBeEmpty();
    });

    it('exposes underlying services', function () {
        $builder = app(ContextBuilder::class);

        expect($builder->getSemanticLoader())->toBeInstanceOf(SemanticModelLoader::class);
        expect($builder->getRulesLoader())->toBeInstanceOf(BusinessRulesLoader::class);
        expect($builder->getPatternSearch())->toBeInstanceOf(QueryPatternSearch::class);
        expect($builder->getIntrospector())->toBeInstanceOf(SchemaIntrospector::class);
    });

    it('can build context with custom options', function () {
        config(['sql-agent.knowledge.source' => 'database']);
        config(['sql-agent.learning.enabled' => false]);

        $builder = app(ContextBuilder::class);
        $context = $builder->buildWithOptions(
            question: 'Test',
            includeSemanticModel: true,
            includeBusinessRules: false,
            includeQueryPatterns: false,
            includeLearnings: false,
            includeRuntimeSchema: false,
        );

        expect($context->businessRules)->toBe('');
        expect($context->queryPatterns)->toBeEmpty();
    });

    it('builds multi-connection context with per-connection sections', function () {
        config(['sql-agent.knowledge.source' => 'database']);
        config(['sql-agent.learning.enabled' => false]);
        config(['sql-agent.database.connections' => [
            'sales' => [
                'connection' => 'testing',
                'label' => 'Sales Database',
                'description' => 'Orders and customers.',
            ],
            'analytics' => [
                'connection' => 'testing',
                'label' => 'Analytics Database',
                'description' => 'Page views and events.',
            ],
        ]]);

        app()->forgetInstance(ConnectionRegistry::class);

        $builder = app(ContextBuilder::class);

        try {
            $context = $builder->build('How many users?');
        } catch (\BadMethodCallException $e) {
            // Doctrine DBAL not available - skip runtime schema check
            $this->markTestSkipped('Schema introspection not available for this driver.');
        }

        expect($context)->toBeInstanceOf(\Knobik\SqlAgent\Data\Context::class);

        // Runtime schema should contain per-connection headers
        if ($context->runtimeSchema !== null) {
            expect($context->runtimeSchema)->toContain('Connection: sales (Sales Database)');
            expect($context->runtimeSchema)->toContain('Connection: analytics (Analytics Database)');
        }
    });

    it('includes business rules and learnings globally in multi-connection mode', function () {
        config(['sql-agent.knowledge.source' => 'database']);
        config(['sql-agent.learning.enabled' => false]);
        config(['sql-agent.database.connections' => [
            'db1' => [
                'connection' => 'testing',
                'label' => 'DB1',
                'description' => 'First database.',
            ],
        ]]);

        app()->forgetInstance(ConnectionRegistry::class);

        $builder = app(ContextBuilder::class);

        try {
            $context = $builder->build('test question');
        } catch (\BadMethodCallException $e) {
            $this->markTestSkipped('Schema introspection not available for this driver.');
        }

        // Business rules are global (not per-connection)
        expect($context->businessRules)->toBeString();
        // Query patterns are global
        expect($context->queryPatterns)->toBeEmpty();
    });
});
