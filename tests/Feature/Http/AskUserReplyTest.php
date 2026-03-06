<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Knobik\SqlAgent\Tests\Feature\Livewire\Helpers;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
    $this->user = Helpers::createAuthenticatedUser();
});

describe('AskUserReplyController', function () {
    it('writes answer to cache with valid data', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.ask-user-reply'), [
                'request_id' => 'sql-agent:ask-user:test-uuid:0',
                'answer' => 'Last month',
            ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        expect(Cache::get('sql-agent:ask-user:test-uuid:0'))->toBe('Last month');
    });

    it('returns 422 when request_id is missing', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.ask-user-reply'), [
                'answer' => 'Last month',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('request_id');
    });

    it('returns 422 when answer is missing', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.ask-user-reply'), [
                'request_id' => 'sql-agent:ask-user:test-uuid:0',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('answer');
    });

    it('returns 422 when answer exceeds max length', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('sql-agent.ask-user-reply'), [
                'request_id' => 'sql-agent:ask-user:test-uuid:0',
                'answer' => str_repeat('x', 1001),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('answer');
    });

    it('uses configured middleware', function () {
        $route = app('router')->getRoutes()->getByName('sql-agent.ask-user-reply');

        expect($route)->not->toBeNull();

        $middleware = $route->middleware();

        expect($middleware)->toContain('web');
    });
});
