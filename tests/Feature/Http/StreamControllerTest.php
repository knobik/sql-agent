<?php

use Knobik\SqlAgent\Http\Requests\StreamRequest;

describe('StreamRequest validation', function () {
    it('requires message field', function () {
        $request = new StreamRequest;
        $rules = $request->rules();

        expect($rules['message'])->toContain('required');
        expect($rules['message'])->toContain('string');
    });

    it('limits message length to 10000', function () {
        $request = new StreamRequest;
        $rules = $request->rules();

        expect($rules['message'])->toContain('max:10000');
    });

    it('conversation_id is nullable integer', function () {
        $request = new StreamRequest;
        $rules = $request->rules();

        expect($rules['conversation_id'])->toContain('nullable');
        expect($rules['conversation_id'])->toContain('integer');
    });

    it('does not have connection validation rule', function () {
        $request = new StreamRequest;
        $rules = $request->rules();

        expect($rules)->not->toHaveKey('connection');
    });

    it('authorizes all requests', function () {
        $request = new StreamRequest;

        expect($request->authorize())->toBeTrue();
    });
});

describe('StreamRequest accessor methods', function () {
    it('getMessage returns message input', function () {
        $request = StreamRequest::create('/stream', 'POST', ['message' => 'How many users?']);

        expect($request->getMessage())->toBe('How many users?');
    });

    it('getConversationId returns null when not provided', function () {
        $request = StreamRequest::create('/stream', 'POST', ['message' => 'test']);

        expect($request->getConversationId())->toBeNull();
    });

    it('getConversationId returns integer', function () {
        $request = StreamRequest::create('/stream', 'POST', [
            'message' => 'test',
            'conversation_id' => '42',
        ]);

        expect($request->getConversationId())->toBe(42);
    });
});
