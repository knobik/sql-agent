<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Message;
use Knobik\SqlAgent\Tests\Feature\Livewire\Helpers;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('sql-agent.user.enabled', true);
});

it('exports conversation as JSON', function () {
    $user = Helpers::createAuthenticatedUser();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'Test Conversation',
        'connection' => 'sqlite',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'Hello world',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Assistant,
        'content' => 'Hi there!',
        'queries' => [
            ['sql' => 'SELECT * FROM users', 'connection' => null],
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('sql-agent.export.json', $conversation->id));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');

    $data = json_decode($response->getContent(), true);

    expect($data['id'])->toBe($conversation->id);
    expect($data['title'])->toBe('Test Conversation');
    expect($data['messages'])->toHaveCount(2);
    expect($data['messages'][0]['role'])->toBe('user');
    expect($data['messages'][0]['content'])->toBe('Hello world');
    expect($data['messages'][1]['role'])->toBe('assistant');
    expect($data['messages'][1]['queries'])->toHaveCount(1);
    expect($data['messages'][1]['queries'][0]['sql'])->toBe('SELECT * FROM users');
});

it('exports conversation as CSV', function () {
    $user = Helpers::createAuthenticatedUser();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'Test Conversation',
        'connection' => 'sqlite',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'Hello world',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Assistant,
        'content' => 'Response',
        'queries' => [
            ['sql' => 'SELECT 1', 'connection' => null],
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('sql-agent.export.csv', $conversation->id));

    $response->assertStatus(200);
    // Charset case varies between PHP/Laravel versions (UTF-8 vs utf-8)
    expect($response->headers->get('Content-Type'))->toMatch('/^text\/csv; charset=utf-8$/i');
});

it('returns 404 for non-existent conversation', function () {
    $user = Helpers::createAuthenticatedUser();

    $response = $this->actingAs($user)
        ->get(route('sql-agent.export.json', 99999));

    $response->assertStatus(404);
});

it('returns 404 when accessing another users conversation', function () {
    $user1 = Helpers::createAuthenticatedUser();
    $user2 = Helpers::createAuthenticatedUser(['email' => 'user2@example.com']);

    $conversation = Conversation::create([
        'user_id' => $user2->id,
        'title' => 'Other User Conversation',
        'connection' => 'sqlite',
    ]);

    $response = $this->actingAs($user1)
        ->get(route('sql-agent.export.json', $conversation->id));

    $response->assertStatus(404);
});
