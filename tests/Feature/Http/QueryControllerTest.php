<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Message;
use Knobik\SqlAgent\Tests\Feature\Livewire\Helpers;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
    config()->set('sql-agent.user.enabled', true);

    DB::statement('CREATE TABLE IF NOT EXISTS test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    DB::table('test_users')->insert([
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ]);

    $this->user = Helpers::createAuthenticatedUser();

    $conversation = Conversation::create(['user_id' => $this->user->id]);
    $this->message = Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Assistant,
        'content' => 'Here are the users.',
        'queries' => [
            ['sql' => 'SELECT * FROM test_users', 'connection' => null],
            ['sql' => 'SELECT name FROM test_users WHERE id = 1', 'connection' => null],
        ],
    ]);
});

afterEach(function () {
    DB::statement('DROP TABLE IF EXISTS test_users');
});

describe('QueryController', function () {
    it('executes a valid query by message id and query index', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.query.execute'), [
                'message_id' => $this->message->id,
                'query_index' => 0,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'rows',
            'row_count',
            'total_rows',
            'truncated',
        ]);
        $response->assertJson([
            'row_count' => 2,
            'total_rows' => 2,
            'truncated' => false,
        ]);
    });

    it('executes a different query index', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.query.execute'), [
                'message_id' => $this->message->id,
                'query_index' => 1,
            ]);

        $response->assertOk();
        $response->assertJson([
            'row_count' => 1,
            'total_rows' => 1,
        ]);
    });

    it('rejects out-of-range query index', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.query.execute'), [
                'message_id' => $this->message->id,
                'query_index' => 99,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Query index out of range.']);
    });

    it('requires message_id and query_index parameters', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.query.execute'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message_id', 'query_index']);
    });

    it('rejects non-existent message id', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.query.execute'), [
                'message_id' => 99999,
                'query_index' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('message_id');
    });

    it('returns structured error for queries that fail at execution', function () {
        $conversation = Conversation::create(['user_id' => $this->user->id]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'Query result.',
            'queries' => [
                ['sql' => 'SELECT * FROM nonexistent_table_xyz', 'connection' => null],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.query.execute'), [
                'message_id' => $message->id,
                'query_index' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
    });

    it('rejects access to another users message', function () {
        $otherUser = Helpers::createAuthenticatedUser();
        $otherConversation = Conversation::create(['user_id' => $otherUser->id]);
        $otherMessage = Message::create([
            'conversation_id' => $otherConversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'Secret data.',
            'queries' => [
                ['sql' => 'SELECT * FROM test_users', 'connection' => null],
            ],
        ]);

        // Try to access other user's message as $this->user
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.query.execute'), [
                'message_id' => $otherMessage->id,
                'query_index' => 0,
            ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Message not found.']);
    });
});
