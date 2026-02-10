<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Contracts\AgentResponse;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Llm\StreamChunk;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Message;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
});

describe('AgentResponse usage', function () {
    it('includes usage data when provided', function () {
        $usage = [
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'cache_write_input_tokens' => null,
            'cache_read_input_tokens' => null,
            'thought_tokens' => null,
        ];

        $response = new AgentResponse(
            answer: 'Test answer',
            usage: $usage,
        );

        expect($response->usage)->toBe($usage);
        expect($response->usage['prompt_tokens'])->toBe(100);
        expect($response->usage['completion_tokens'])->toBe(50);
    });

    it('defaults usage to null', function () {
        $response = new AgentResponse(answer: 'Test answer');

        expect($response->usage)->toBeNull();
    });
});

describe('StreamChunk usage', function () {
    it('includes usage in complete chunk', function () {
        $usage = [
            'prompt_tokens' => 200,
            'completion_tokens' => 100,
            'cache_write_input_tokens' => 50,
            'cache_read_input_tokens' => 30,
            'thought_tokens' => 10,
        ];

        $chunk = StreamChunk::complete('stop', usage: $usage);

        expect($chunk->isComplete())->toBeTrue();
        expect($chunk->usage)->toBe($usage);
        expect($chunk->usage['prompt_tokens'])->toBe(200);
    });

    it('has null usage by default', function () {
        $chunk = StreamChunk::complete('stop');

        expect($chunk->usage)->toBeNull();
    });

    it('has null usage for content chunks', function () {
        $chunk = StreamChunk::content('hello');

        expect($chunk->usage)->toBeNull();
    });
});

describe('Message usage accessor', function () {
    it('extracts usage from metadata', function () {
        $conversation = Conversation::create([]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'Test',
            'metadata' => [
                'usage' => [
                    'prompt_tokens' => 150,
                    'completion_tokens' => 75,
                    'cache_write_input_tokens' => null,
                    'cache_read_input_tokens' => null,
                    'thought_tokens' => null,
                ],
            ],
        ]);

        expect($message->usage)->toBeArray();
        expect($message->usage['prompt_tokens'])->toBe(150);
        expect($message->usage['completion_tokens'])->toBe(75);
    });

    it('returns null when no usage in metadata', function () {
        $conversation = Conversation::create([]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'Test',
            'metadata' => ['thinking' => 'some thoughts'],
        ]);

        expect($message->usage)->toBeNull();
    });

    it('returns null when no metadata', function () {
        $conversation = Conversation::create([]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'Test',
        ]);

        expect($message->usage)->toBeNull();
    });
});
