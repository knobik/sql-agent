<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Message;
use Knobik\SqlAgent\Services\ConversationService;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('sql-agent.user.enabled', false);
});

describe('findOrCreate', function () {
    test('creates a new conversation when no id provided', function () {
        $service = app(ConversationService::class);

        $conversation = $service->findOrCreate(null, 'mysql');

        expect($conversation)->toBeInstanceOf(Conversation::class);
        expect($conversation->getAttribute('connection'))->toBe('mysql');
    });

    test('returns existing conversation when valid id provided', function () {
        $existing = Conversation::create(['connection' => 'mysql']);
        $service = app(ConversationService::class);

        $conversation = $service->findOrCreate($existing->id, 'mysql');

        expect($conversation->id)->toBe($existing->id);
    });

    test('creates new conversation when id not found', function () {
        $service = app(ConversationService::class);

        $conversation = $service->findOrCreate(999, 'mysql');

        expect($conversation->id)->not->toBe(999);
    });
});

describe('addMessage', function () {
    test('creates a user message', function () {
        $conversation = Conversation::create(['connection' => 'mysql']);
        $service = app(ConversationService::class);

        $message = $service->addMessage($conversation->id, MessageRole::User, 'Hello');

        expect($message)->toBeInstanceOf(Message::class);
        expect($message->content)->toBe('Hello');
        expect($message->role)->toBe(MessageRole::User);
        expect($message->conversation_id)->toBe($conversation->id);
    });

    test('creates an assistant message with queries', function () {
        $conversation = Conversation::create(['connection' => 'mysql']);
        $service = app(ConversationService::class);

        $queries = [
            ['sql' => 'SELECT * FROM users', 'connection' => null],
        ];

        $message = $service->addMessage(
            $conversation->id,
            MessageRole::Assistant,
            'Here are the results',
            $queries,
            ['thinking' => 'some thoughts'],
        );

        expect($message->queries)->toBe($queries);
        expect($message->metadata)->toBe(['thinking' => 'some thoughts']);
    });
});

describe('getHistory', function () {
    test('returns formatted history', function () {
        $conversation = Conversation::create(['connection' => 'mysql']);
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::User,
            'content' => 'First question',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'First answer',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::User,
            'content' => 'Current question',
        ]);
        $service = app(ConversationService::class);

        $history = $service->getHistory($conversation->id);

        // Should exclude the last user message (current question)
        expect($history)->toHaveCount(2);
        expect($history[0]['role'])->toBe('user');
        expect($history[0]['content'])->toBe('First question');
        expect($history[1]['role'])->toBe('assistant');
        expect($history[1]['content'])->toBe('First answer');
    });

    test('returns empty array for new conversation', function () {
        $conversation = Conversation::create(['connection' => 'mysql']);
        $service = app(ConversationService::class);

        expect($service->getHistory($conversation->id))->toBeEmpty();
    });
});
