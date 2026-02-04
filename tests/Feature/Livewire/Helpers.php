<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tests\Feature\Livewire;

use Illuminate\Support\Facades\Auth;

class Helpers
{
    private static int $nextUserId = 1;

    public static function createTestUser(array $attributes = []): object
    {
        return new TestUser($attributes, self::$nextUserId++);
    }

    public static function actingAs(object $user): void
    {
        Auth::shouldReceive('id')->andReturn($user->id);
        Auth::shouldReceive('user')->andReturn($user);
        Auth::shouldReceive('check')->andReturn(true);
    }

    public static function createAuthenticatedUser(array $attributes = []): object
    {
        return new AuthenticatedTestUser($attributes, self::$nextUserId++);
    }
}

class TestUser
{
    public int $id;

    public string $email;

    public function __construct(array $attributes, int $id)
    {
        $this->id = $id;
        $this->email = $attributes['email'] ?? 'test@example.com';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }
}

class AuthenticatedTestUser implements \Illuminate\Contracts\Auth\Authenticatable
{
    public int $id;

    public string $email;

    public function __construct(array $attributes, int $id)
    {
        $this->id = $id;
        $this->email = $attributes['email'] ?? 'test@example.com';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthPassword()
    {
        return 'password';
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value) {}

    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function getAuthPasswordName()
    {
        return 'password';
    }
}
