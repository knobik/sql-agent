<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Livewire\ConversationList;
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

it('can render conversation list', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    \Livewire\Livewire::test(ConversationList::class)
        ->assertStatus(200)
        ->assertSee('Conversations');
});

it('shows empty state when no conversations', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    \Livewire\Livewire::test(ConversationList::class)
        ->assertSee('No conversations yet');
});

it('lists user conversations', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    Conversation::create([
        'user_id' => $user->id,
        'title' => 'First Conversation',
        'connection' => 'sqlite',
    ]);

    Conversation::create([
        'user_id' => $user->id,
        'title' => 'Second Conversation',
        'connection' => 'sqlite',
    ]);

    \Livewire\Livewire::test(ConversationList::class)
        ->assertSee('First Conversation')
        ->assertSee('Second Conversation');
});

it('does not show conversations from other users', function () {
    $user1 = Helpers::createTestUser();
    $user2 = Helpers::createTestUser(['email' => 'user2@example.com']);

    Conversation::create([
        'user_id' => $user1->id,
        'title' => 'User 1 Conversation',
        'connection' => 'sqlite',
    ]);

    Conversation::create([
        'user_id' => $user2->id,
        'title' => 'User 2 Conversation',
        'connection' => 'sqlite',
    ]);

    Helpers::actingAs($user1);

    \Livewire\Livewire::test(ConversationList::class)
        ->assertSee('User 1 Conversation')
        ->assertDontSee('User 2 Conversation');
});

it('can search conversations', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    Conversation::create([
        'user_id' => $user->id,
        'title' => 'Sales Report Query',
        'connection' => 'sqlite',
    ]);

    Conversation::create([
        'user_id' => $user->id,
        'title' => 'User Analytics',
        'connection' => 'sqlite',
    ]);

    \Livewire\Livewire::test(ConversationList::class)
        ->set('search', 'Sales')
        ->assertSee('Sales Report Query')
        ->assertDontSee('User Analytics');
});

it('can select a conversation', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'Test Conversation',
        'connection' => 'sqlite',
    ]);

    \Livewire\Livewire::test(ConversationList::class)
        ->call('selectConversation', $conversation->id)
        ->assertSet('selectedConversationId', $conversation->id)
        ->assertDispatched('load-conversation', conversationId: $conversation->id);
});

it('can create new conversation', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    \Livewire\Livewire::test(ConversationList::class)
        ->call('newConversation')
        ->assertSet('selectedConversationId', null)
        ->assertDispatched('new-conversation');
});

it('can confirm delete conversation', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'To Delete',
        'connection' => 'sqlite',
    ]);

    \Livewire\Livewire::test(ConversationList::class)
        ->call('confirmDelete', $conversation->id)
        ->assertSet('deleteConversationId', $conversation->id)
        ->assertSet('showDeleteConfirm', true);
});

it('can cancel delete', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'To Delete',
        'connection' => 'sqlite',
    ]);

    \Livewire\Livewire::test(ConversationList::class)
        ->call('confirmDelete', $conversation->id)
        ->call('cancelDelete')
        ->assertSet('deleteConversationId', null)
        ->assertSet('showDeleteConfirm', false);
});

it('can delete conversation', function () {
    $user = Helpers::createTestUser();
    Helpers::actingAs($user);

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'To Delete',
        'connection' => 'sqlite',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'Test message',
    ]);

    \Livewire\Livewire::test(ConversationList::class)
        ->call('confirmDelete', $conversation->id)
        ->call('deleteConversation')
        ->assertSet('showDeleteConfirm', false);

    expect(Conversation::find($conversation->id))->toBeNull();
    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(0);
});

it('cannot delete another users conversation', function () {
    $user1 = Helpers::createTestUser();
    $user2 = Helpers::createTestUser(['email' => 'user2@example.com']);

    $conversation = Conversation::create([
        'user_id' => $user2->id,
        'title' => 'Other User Conversation',
        'connection' => 'sqlite',
    ]);

    Helpers::actingAs($user1);

    \Livewire\Livewire::test(ConversationList::class)
        ->set('deleteConversationId', $conversation->id)
        ->set('showDeleteConfirm', true)
        ->call('deleteConversation');

    // Conversation should still exist
    expect(Conversation::find($conversation->id))->not->toBeNull();
});
