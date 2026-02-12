<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Message;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
});

describe('Message', function () {
    it('can be created', function () {
        $conversation = Conversation::create([]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::User,
            'content' => 'Test message',
        ]);

        expect($message->role)->toBe(MessageRole::User);
        expect($message->isFromUser())->toBeTrue();
    });

    it('can have queries', function () {
        $conversation = Conversation::create([]);
        $queries = [
            ['sql' => 'SELECT * FROM users', 'connection' => null],
            ['sql' => 'SELECT count(*) FROM orders', 'connection' => 'analytics'],
        ];
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'Here are the results',
            'queries' => $queries,
        ]);

        expect($message->hasQueries())->toBeTrue();
        expect($message->getQueries())->toHaveCount(2);
        expect($message->getQueries()[0]['sql'])->toBe('SELECT * FROM users');
    });

    it('returns empty queries when null', function () {
        $conversation = Conversation::create([]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'No queries here',
        ]);

        expect($message->hasQueries())->toBeFalse();
        expect($message->getQueries())->toBeEmpty();
    });

    it('scopes by role', function () {
        $conversation = Conversation::create([]);
        Message::create(['conversation_id' => $conversation->id, 'role' => MessageRole::User, 'content' => 'Q']);
        Message::create(['conversation_id' => $conversation->id, 'role' => MessageRole::Assistant, 'content' => 'A']);

        expect(Message::fromUser()->count())->toBe(1);
        expect(Message::fromAssistant()->count())->toBe(1);
    });
});
