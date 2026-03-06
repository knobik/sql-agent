<?php

use Illuminate\Support\Facades\Cache;
use Knobik\SqlAgent\Tools\AskUserTool;

beforeEach(function () {
    $this->tool = new AskUserTool;
});

describe('AskUserTool registration', function () {
    it('has the correct tool name', function () {
        expect($this->tool->name())->toBe('ask_user');
    });

    it('has question parameter', function () {
        $params = $this->tool->parametersAsArray();

        expect($params)->toHaveKey('question');
    });

    it('has optional suggestions parameter', function () {
        $params = $this->tool->parametersAsArray();

        expect($params)->toHaveKey('suggestions');
    });

    it('has optional multiple parameter', function () {
        $params = $this->tool->parametersAsArray();

        expect($params)->toHaveKey('multiple');
    });

    it('has a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });
});

describe('non-interactive mode', function () {
    it('returns fallback when no callback is set', function () {
        $result = ($this->tool)('What period?', [['label' => 'Last week'], ['label' => 'Last month']]);

        expect($result)->toContain('not available');
        expect($result)->toContain('best guess');
    });
});

describe('suggestion parsing', function () {
    it('handles null suggestions', function () {
        $reflection = new ReflectionMethod($this->tool, 'parseSuggestions');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->tool, null);

        expect($result)->toBe([]);
    });

    it('handles empty array suggestions', function () {
        $reflection = new ReflectionMethod($this->tool, 'parseSuggestions');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->tool, []);

        expect($result)->toBe([]);
    });

    it('parses object suggestions with label and description', function () {
        $reflection = new ReflectionMethod($this->tool, 'parseSuggestions');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->tool, [
            ['label' => 'Revenue trends', 'description' => 'Monthly revenue over time'],
            ['label' => 'Customer segments'],
        ]);

        expect($result)->toBe([
            ['label' => 'Revenue trends', 'description' => 'Monthly revenue over time'],
            ['label' => 'Customer segments', 'description' => null],
        ]);
    });

    it('trims labels and descriptions', function () {
        $reflection = new ReflectionMethod($this->tool, 'parseSuggestions');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->tool, [
            ['label' => '  Revenue  ', 'description' => '  Monthly revenue  '],
        ]);

        expect($result)->toBe([
            ['label' => 'Revenue', 'description' => 'Monthly revenue'],
        ]);
    });

    it('filters out entries with empty labels', function () {
        $reflection = new ReflectionMethod($this->tool, 'parseSuggestions');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->tool, [
            ['label' => ''],
            ['label' => '  '],
            ['label' => 'Valid'],
        ]);

        expect($result)->toBe([
            ['label' => 'Valid', 'description' => null],
        ]);
    });

    it('handles plain string suggestions as backward compatibility', function () {
        $reflection = new ReflectionMethod($this->tool, 'parseSuggestions');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->tool, ['First', 'Second', 'Third']);

        expect($result)->toBe([
            ['label' => 'First', 'description' => null],
            ['label' => 'Second', 'description' => null],
            ['label' => 'Third', 'description' => null],
        ]);
    });

    it('filters non-string and non-array values', function () {
        $reflection = new ReflectionMethod($this->tool, 'parseSuggestions');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->tool, ['Valid', 123, null, ['label' => 'Also valid']]);

        expect($result)->toBe([
            ['label' => 'Valid', 'description' => null],
            ['label' => 'Also valid', 'description' => null],
        ]);
    });

    it('filters entries without a label key', function () {
        $reflection = new ReflectionMethod($this->tool, 'parseSuggestions');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->tool, [
            ['description' => 'No label here'],
            ['label' => 'Has label'],
        ]);

        expect($result)->toBe([
            ['label' => 'Has label', 'description' => null],
        ]);
    });
});

describe('cache polling', function () {
    it('returns answer when found in cache with suggestions', function () {
        $sentData = null;
        $this->tool->setRequestId('test-uuid');
        $this->tool->setSendCallback(function (array $data) use (&$sentData) {
            $sentData = $data;
            Cache::put($data['request_id'], 'Last month', now()->addMinutes(10));
        });

        $result = ($this->tool)('What period?', [['label' => 'Last week'], ['label' => 'Last month']]);

        expect($result)->toBe('User answered: Last month');
        expect($sentData)->not->toBeNull();
        expect($sentData['question'])->toBe('What period?');
        expect($sentData['suggestions'])->toBe([
            ['label' => 'Last week', 'description' => null],
            ['label' => 'Last month', 'description' => null],
        ]);
        expect($sentData['multiple'])->toBeFalse();
        expect($sentData['request_id'])->toStartWith('sql-agent:ask-user:test-uuid:');
    });

    it('sends multiple flag when set to true', function () {
        $sentData = null;
        $this->tool->setRequestId('test-uuid');
        $this->tool->setSendCallback(function (array $data) use (&$sentData) {
            $sentData = $data;
            Cache::put($data['request_id'], 'A, B', now()->addMinutes(10));
        });

        ($this->tool)('Pick items', [['label' => 'A'], ['label' => 'B']], true);

        expect($sentData['multiple'])->toBeTrue();
    });

    it('returns answer when found in cache without suggestions', function () {
        $sentData = null;
        $this->tool->setRequestId('test-uuid');
        $this->tool->setSendCallback(function (array $data) use (&$sentData) {
            $sentData = $data;
            Cache::put($data['request_id'], 'Custom answer from user', now()->addMinutes(10));
        });

        $result = ($this->tool)('What do you want to know?');

        expect($result)->toBe('User answered: Custom answer from user');
        expect($sentData['suggestions'])->toBe([]);
        expect($sentData['multiple'])->toBeFalse();
    });

    it('cleans up cache key after reading', function () {
        $cacheKey = null;
        $this->tool->setRequestId('test-uuid');
        $this->tool->setSendCallback(function (array $data) use (&$cacheKey) {
            $cacheKey = $data['request_id'];
            Cache::put($data['request_id'], 'Yes', now()->addMinutes(10));
        });

        ($this->tool)('Continue?', [['label' => 'Yes'], ['label' => 'No']]);

        expect(Cache::get($cacheKey))->toBeNull();
    });

    it('returns timeout fallback when no answer received', function () {
        config(['sql-agent.agent.ask_user_timeout' => 1]); // 1 second timeout

        $this->tool->setRequestId('test-uuid');
        $this->tool->setSendCallback(function (array $data) {
            // Don't write to cache - simulate no answer
        });

        $result = ($this->tool)('What period?', [['label' => 'Last week']]);

        expect($result)->toContain('did not respond');
        expect($result)->toContain('best guess');
    });

    it('increments invocation counter for unique cache keys', function () {
        $cacheKeys = [];
        $this->tool->setRequestId('test-uuid');
        $this->tool->setSendCallback(function (array $data) use (&$cacheKeys) {
            $cacheKeys[] = $data['request_id'];
            Cache::put($data['request_id'], 'Answer', now()->addMinutes(10));
        });

        ($this->tool)('First question?', [['label' => 'A'], ['label' => 'B']]);
        ($this->tool)('Second question?', [['label' => 'C'], ['label' => 'D']]);

        expect($cacheKeys)->toHaveCount(2);
        expect($cacheKeys[0])->not->toBe($cacheKeys[1]);
        expect($cacheKeys[0])->toBe('sql-agent:ask-user:test-uuid:0');
        expect($cacheKeys[1])->toBe('sql-agent:ask-user:test-uuid:1');
    });
});

describe('reset', function () {
    it('resets invocation counter', function () {
        $sentData = [];
        $this->tool->setRequestId('test-uuid');
        $this->tool->setSendCallback(function (array $data) use (&$sentData) {
            $sentData[] = $data;
        });

        // First invocation uses counter 0
        Cache::shouldReceive('get')->once()->andReturn('answer');
        Cache::shouldReceive('forget')->once();
        ($this->tool)('Q1?', [['label' => 'A'], ['label' => 'B']]);

        expect($sentData[0]['request_id'])->toContain(':0');

        // Reset and invoke again - counter should restart at 0
        $this->tool->reset();

        Cache::shouldReceive('get')->once()->andReturn('answer');
        Cache::shouldReceive('forget')->once();
        ($this->tool)('Q2?', [['label' => 'A'], ['label' => 'B']]);

        expect($sentData[1]['request_id'])->toContain(':0');
    });

    it('preserves callback and request id after reset', function () {
        $called = false;
        $this->tool->setRequestId('test-uuid');
        $this->tool->setSendCallback(function () use (&$called) {
            $called = true;
        });

        $this->tool->reset();

        Cache::shouldReceive('get')->once()->andReturn('answer');
        Cache::shouldReceive('forget')->once();
        $result = ($this->tool)('Question?', [['label' => 'A'], ['label' => 'B']]);

        expect($called)->toBeTrue();
        expect($result)->toContain('User answered: answer');
    });
});
