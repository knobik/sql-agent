<?php

use Knobik\SqlAgent\Services\TableAccessControl;

describe('TableAccessControl', function () {
    describe('filterTables', function () {
        it('allows all tables when no connection specified', function () {
            $service = app(TableAccessControl::class);

            $result = $service->filterTables(['users', 'orders', 'products']);

            expect($result)->toBe(['users', 'orders', 'products']);
        });

        it('filters by per-connection allowed_tables whitelist', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => ['users', 'orders'],
                    'denied_tables' => [],
                    'hidden_columns' => [],
                ],
            ]);

            $service = app(TableAccessControl::class);

            $result = $service->filterTables(['users', 'orders', 'products', 'secrets'], 'sales');

            expect($result)->toBe(['users', 'orders']);
        });

        it('filters by per-connection denied_tables blacklist', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => [],
                    'denied_tables' => ['secrets', 'audit_log'],
                    'hidden_columns' => [],
                ],
            ]);

            $service = app(TableAccessControl::class);

            $result = $service->filterTables(['users', 'orders', 'secrets', 'audit_log'], 'sales');

            expect($result)->toBe(['users', 'orders']);
        });

        it('denied takes precedence over allowed', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => ['users', 'orders', 'secrets'],
                    'denied_tables' => ['secrets'],
                    'hidden_columns' => [],
                ],
            ]);

            $service = app(TableAccessControl::class);

            $result = $service->filterTables(['users', 'orders', 'secrets', 'products'], 'sales');

            expect($result)->toBe(['users', 'orders']);
        });

        it('returns empty array when all tables denied', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => [],
                    'denied_tables' => ['users', 'orders'],
                    'hidden_columns' => [],
                ],
            ]);

            $service = app(TableAccessControl::class);

            $result = $service->filterTables(['users', 'orders'], 'sales');

            expect($result)->toBe([]);
        });
    });

    describe('isTableAllowed', function () {
        it('allows any table when no connection specified', function () {
            $service = app(TableAccessControl::class);

            expect($service->isTableAllowed('users'))->toBeTrue();
            expect($service->isTableAllowed('anything'))->toBeTrue();
        });

        it('denies table in per-connection denied_tables', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => [],
                    'denied_tables' => ['secrets'],
                    'hidden_columns' => [],
                ],
            ]);

            $service = app(TableAccessControl::class);

            expect($service->isTableAllowed('secrets', 'sales'))->toBeFalse();
            expect($service->isTableAllowed('users', 'sales'))->toBeTrue();
        });

        it('only allows tables in per-connection allowed_tables when set', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => ['users', 'orders'],
                    'denied_tables' => [],
                    'hidden_columns' => [],
                ],
            ]);

            $service = app(TableAccessControl::class);

            expect($service->isTableAllowed('users', 'sales'))->toBeTrue();
            expect($service->isTableAllowed('orders', 'sales'))->toBeTrue();
            expect($service->isTableAllowed('products', 'sales'))->toBeFalse();
        });

        it('denied_tables takes precedence over allowed_tables', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => ['users', 'secrets'],
                    'denied_tables' => ['secrets'],
                    'hidden_columns' => [],
                ],
            ]);

            $service = app(TableAccessControl::class);

            expect($service->isTableAllowed('secrets', 'sales'))->toBeFalse();
            expect($service->isTableAllowed('users', 'sales'))->toBeTrue();
        });
    });

    describe('filterColumns', function () {
        it('returns all columns when no connection specified', function () {
            $service = app(TableAccessControl::class);

            $columns = ['id' => 'integer', 'name' => 'string', 'email' => 'string'];
            $result = $service->filterColumns('users', $columns);

            expect($result)->toBe($columns);
        });

        it('removes per-connection hidden columns for specified table', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => [],
                    'denied_tables' => [],
                    'hidden_columns' => [
                        'users' => ['password', 'remember_token'],
                    ],
                ],
            ]);

            $service = app(TableAccessControl::class);

            $columns = [
                'id' => 'integer',
                'name' => 'string',
                'password' => 'string',
                'remember_token' => 'string',
            ];

            $result = $service->filterColumns('users', $columns, 'sales');

            expect($result)->toBe(['id' => 'integer', 'name' => 'string']);
        });

        it('does not affect columns of other tables', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => [],
                    'denied_tables' => [],
                    'hidden_columns' => [
                        'users' => ['password'],
                    ],
                ],
            ]);

            $service = app(TableAccessControl::class);

            $columns = ['id' => 'integer', 'password' => 'string'];
            $result = $service->filterColumns('orders', $columns, 'sales');

            expect($result)->toBe($columns);
        });
    });

    describe('getHiddenColumns', function () {
        it('returns empty array when no connection specified', function () {
            $service = app(TableAccessControl::class);

            expect($service->getHiddenColumns('users'))->toBe([]);
        });

        it('returns per-connection hidden columns for specified table', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'testing',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => [],
                    'denied_tables' => [],
                    'hidden_columns' => [
                        'users' => ['password', 'remember_token'],
                    ],
                ],
            ]);

            $service = app(TableAccessControl::class);

            expect($service->getHiddenColumns('users', 'sales'))->toBe(['password', 'remember_token']);
        });
    });

    describe('per-connection rules', function () {
        beforeEach(function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'mysql_sales',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                    'allowed_tables' => ['orders', 'products'],
                    'denied_tables' => ['audit_log'],
                    'hidden_columns' => ['orders' => ['internal_notes']],
                ],
                'hr' => [
                    'connection' => 'pgsql_hr',
                    'label' => 'HR',
                    'description' => 'HR database.',
                    'allowed_tables' => [],
                    'denied_tables' => ['salary_details'],
                    'hidden_columns' => [],
                ],
            ]);
        });

        it('uses per-connection allowed_tables', function () {
            $service = app(TableAccessControl::class);

            expect($service->isTableAllowed('orders', 'sales'))->toBeTrue();
            expect($service->isTableAllowed('products', 'sales'))->toBeTrue();
            expect($service->isTableAllowed('users', 'sales'))->toBeFalse();
        });

        it('uses per-connection denied_tables', function () {
            $service = app(TableAccessControl::class);

            expect($service->isTableAllowed('audit_log', 'sales'))->toBeFalse();
        });

        it('per-connection denied takes precedence over allowed', function () {
            // Configure a connection where audit_log is both allowed and denied
            config()->set('sql-agent.database.connections.conflict', [
                'connection' => 'conn',
                'label' => 'Conflict',
                'description' => 'Test.',
                'allowed_tables' => ['audit_log'],
                'denied_tables' => ['audit_log'],
                'hidden_columns' => [],
            ]);

            $service = app(TableAccessControl::class);

            expect($service->isTableAllowed('audit_log', 'conflict'))->toBeFalse();
        });

        it('connection with empty allowed_tables allows all non-denied', function () {
            $service = app(TableAccessControl::class);

            expect($service->isTableAllowed('anything', 'hr'))->toBeTrue();
            expect($service->isTableAllowed('salary_details', 'hr'))->toBeFalse();
        });

        it('uses per-connection hidden_columns', function () {
            $service = app(TableAccessControl::class);

            expect($service->getHiddenColumns('orders', 'sales'))->toBe(['internal_notes']);
            expect($service->getHiddenColumns('orders', 'hr'))->toBe([]);
        });

        it('filters tables per-connection', function () {
            $service = app(TableAccessControl::class);

            $result = $service->filterTables(['orders', 'users', 'audit_log', 'products'], 'sales');

            expect($result)->toBe(['orders', 'products']);
        });

        it('filters columns per-connection', function () {
            $service = app(TableAccessControl::class);

            $columns = ['id' => 'int', 'total' => 'decimal', 'internal_notes' => 'text'];
            $result = $service->filterColumns('orders', $columns, 'sales');

            expect($result)->toBe(['id' => 'int', 'total' => 'decimal']);
        });

        it('returns no restrictions when no connection name given', function () {
            $service = app(TableAccessControl::class);

            // Without connection name, all tables are allowed (no restrictions)
            expect($service->isTableAllowed('orders'))->toBeTrue();
            expect($service->isTableAllowed('anything'))->toBeTrue();
        });
    });

});
