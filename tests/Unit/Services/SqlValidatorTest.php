<?php

use Knobik\SqlAgent\Services\SqlValidator;

describe('SqlValidator', function () {
    it('allows valid SELECT statements', function () {
        $validator = app(SqlValidator::class);

        // Should not throw
        $validator->validate('SELECT * FROM users');

        expect(true)->toBeTrue();
    });

    it('allows WITH (CTE) statements', function () {
        $validator = app(SqlValidator::class);

        $validator->validate('WITH cte AS (SELECT 1) SELECT * FROM cte');

        expect(true)->toBeTrue();
    });

    it('rejects INSERT statements', function () {
        $validator = app(SqlValidator::class);

        expect(fn () => $validator->validate("INSERT INTO users (name) VALUES ('Test')"))
            ->toThrow(RuntimeException::class, 'Only');
    });

    it('rejects DROP statements', function () {
        $validator = app(SqlValidator::class);

        expect(fn () => $validator->validate('DROP TABLE users'))
            ->toThrow(RuntimeException::class, 'Only');
    });

    it('rejects multiple statements', function () {
        $validator = app(SqlValidator::class);

        expect(fn () => $validator->validate('SELECT 1; DELETE FROM users'))
            ->toThrow(RuntimeException::class);
    });

    it('rejects forbidden keywords', function () {
        $validator = app(SqlValidator::class);

        expect(fn () => $validator->validate('SELECT * FROM users; DELETE FROM users'))
            ->toThrow(RuntimeException::class);
    });
});
