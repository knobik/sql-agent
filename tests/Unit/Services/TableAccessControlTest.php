<?php

use Knobik\SqlAgent\Services\TableAccessControl;

describe('TableAccessControl', function () {
    describe('filterTables', function () {
        it('allows all tables when no restrictions configured', function () {
            config()->set('sql-agent.sql.allowed_tables', []);
            config()->set('sql-agent.sql.denied_tables', []);

            $service = new TableAccessControl;

            $result = $service->filterTables(['users', 'orders', 'products']);

            expect($result)->toBe(['users', 'orders', 'products']);
        });

        it('filters by allowed_tables whitelist', function () {
            config()->set('sql-agent.sql.allowed_tables', ['users', 'orders']);
            config()->set('sql-agent.sql.denied_tables', []);

            $service = new TableAccessControl;

            $result = $service->filterTables(['users', 'orders', 'products', 'secrets']);

            expect($result)->toBe(['users', 'orders']);
        });

        it('filters by denied_tables blacklist', function () {
            config()->set('sql-agent.sql.allowed_tables', []);
            config()->set('sql-agent.sql.denied_tables', ['secrets', 'audit_log']);

            $service = new TableAccessControl;

            $result = $service->filterTables(['users', 'orders', 'secrets', 'audit_log']);

            expect($result)->toBe(['users', 'orders']);
        });

        it('denied takes precedence over allowed', function () {
            config()->set('sql-agent.sql.allowed_tables', ['users', 'orders', 'secrets']);
            config()->set('sql-agent.sql.denied_tables', ['secrets']);

            $service = new TableAccessControl;

            $result = $service->filterTables(['users', 'orders', 'secrets', 'products']);

            expect($result)->toBe(['users', 'orders']);
        });

        it('returns empty array when all tables denied', function () {
            config()->set('sql-agent.sql.allowed_tables', []);
            config()->set('sql-agent.sql.denied_tables', ['users', 'orders']);

            $service = new TableAccessControl;

            $result = $service->filterTables(['users', 'orders']);

            expect($result)->toBe([]);
        });
    });

    describe('isTableAllowed', function () {
        it('allows any table when no restrictions', function () {
            config()->set('sql-agent.sql.allowed_tables', []);
            config()->set('sql-agent.sql.denied_tables', []);

            $service = new TableAccessControl;

            expect($service->isTableAllowed('users'))->toBeTrue();
            expect($service->isTableAllowed('anything'))->toBeTrue();
        });

        it('denies table in denied_tables', function () {
            config()->set('sql-agent.sql.allowed_tables', []);
            config()->set('sql-agent.sql.denied_tables', ['secrets']);

            $service = new TableAccessControl;

            expect($service->isTableAllowed('secrets'))->toBeFalse();
            expect($service->isTableAllowed('users'))->toBeTrue();
        });

        it('only allows tables in allowed_tables when set', function () {
            config()->set('sql-agent.sql.allowed_tables', ['users', 'orders']);
            config()->set('sql-agent.sql.denied_tables', []);

            $service = new TableAccessControl;

            expect($service->isTableAllowed('users'))->toBeTrue();
            expect($service->isTableAllowed('orders'))->toBeTrue();
            expect($service->isTableAllowed('products'))->toBeFalse();
        });

        it('denied_tables takes precedence over allowed_tables', function () {
            config()->set('sql-agent.sql.allowed_tables', ['users', 'secrets']);
            config()->set('sql-agent.sql.denied_tables', ['secrets']);

            $service = new TableAccessControl;

            expect($service->isTableAllowed('secrets'))->toBeFalse();
            expect($service->isTableAllowed('users'))->toBeTrue();
        });
    });

    describe('filterColumns', function () {
        it('returns all columns when no hidden columns configured', function () {
            config()->set('sql-agent.sql.hidden_columns', []);

            $service = new TableAccessControl;

            $columns = ['id' => 'integer', 'name' => 'string', 'email' => 'string'];
            $result = $service->filterColumns('users', $columns);

            expect($result)->toBe($columns);
        });

        it('removes hidden columns for specified table', function () {
            config()->set('sql-agent.sql.hidden_columns', [
                'users' => ['password', 'remember_token'],
            ]);

            $service = new TableAccessControl;

            $columns = [
                'id' => 'integer',
                'name' => 'string',
                'password' => 'string',
                'remember_token' => 'string',
            ];

            $result = $service->filterColumns('users', $columns);

            expect($result)->toBe(['id' => 'integer', 'name' => 'string']);
        });

        it('does not affect columns of other tables', function () {
            config()->set('sql-agent.sql.hidden_columns', [
                'users' => ['password'],
            ]);

            $service = new TableAccessControl;

            $columns = ['id' => 'integer', 'password' => 'string'];
            $result = $service->filterColumns('orders', $columns);

            expect($result)->toBe($columns);
        });
    });

    describe('getHiddenColumns', function () {
        it('returns empty array when no hidden columns for table', function () {
            config()->set('sql-agent.sql.hidden_columns', []);

            $service = new TableAccessControl;

            expect($service->getHiddenColumns('users'))->toBe([]);
        });

        it('returns hidden columns for specified table', function () {
            config()->set('sql-agent.sql.hidden_columns', [
                'users' => ['password', 'remember_token'],
            ]);

            $service = new TableAccessControl;

            expect($service->getHiddenColumns('users'))->toBe(['password', 'remember_token']);
        });
    });

});
