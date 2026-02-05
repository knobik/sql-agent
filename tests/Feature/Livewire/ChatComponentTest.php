<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Livewire\ChatComponent;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Message;
use Knobik\SqlAgent\Tests\Feature\Livewire\Helpers;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Skip tests if Livewire is not available
    if (! class_exists(\Livewire\Livewire::class)) {
        $this->markTestSkipped('Livewire is not installed');
    }
});

it('can render chat component', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    \Livewire\Livewire::test(ChatComponent::class)
        ->assertStatus(200)
        ->assertSee('Ask a question about your data');
});

it('can load an existing conversation', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'Test Conversation',
        'connection' => 'sqlite',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'Hello',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Assistant,
        'content' => 'Hi there!',
    ]);

    \Livewire\Livewire::test(ChatComponent::class, ['conversationId' => $conversation->id])
        ->assertSet('conversationId', $conversation->id)
        ->assertSee('Hello')
        ->assertSee('Hi there!');
});

it('prevents loading conversation from another user when user tracking is enabled', function () {
    // Enable user tracking for this test
    config(['sql-agent.user.enabled' => true]);

    $user1 = Helpers::createTestUser();
    $user2 = Helpers::createTestUser(['email' => 'user2@example.com']);

    $conversation = Conversation::create([
        'user_id' => $user2->id,
        'title' => 'Other User Conversation',
        'connection' => 'sqlite',
    ]);

    Helpers::actingAs($user1);

    \Livewire\Livewire::test(ChatComponent::class, ['conversationId' => $conversation->id])
        ->assertSet('conversationId', null);
});

it('can create a new conversation', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'Existing Conversation',
        'connection' => 'sqlite',
    ]);

    \Livewire\Livewire::test(ChatComponent::class, ['conversationId' => $conversation->id])
        ->call('newConversation')
        ->assertSet('conversationId', null);
});

it('shows empty state when no conversation', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    \Livewire\Livewire::test(ChatComponent::class)
        ->assertSee('Ask a question about your data')
        ->assertSee('Show me the top 10 customers by total orders');
});

it('streaming endpoint route exists', function () {
    // Just verify the route is registered
    expect(route('sql-agent.stream'))->toContain('sql-agent/stream');
});
