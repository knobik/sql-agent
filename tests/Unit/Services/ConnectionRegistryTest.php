<?php

use Knobik\SqlAgent\Data\ConnectionConfig;
use Knobik\SqlAgent\Services\ConnectionRegistry;

describe('ConnectionRegistry', function () {
    describe('getConnectionNames', function () {
        it('returns empty array when no connections configured', function () {
            config()->set('sql-agent.database.connections', []);

            $registry = new ConnectionRegistry;

            expect($registry->getConnectionNames())->toBe([]);
        });

        it('returns logical names as keys', function () {
            config()->set('sql-agent.database.connections', [
                'crm' => [
                    'connection' => 'mysql_crm',
                    'label' => 'CRM',
                    'description' => 'CRM database.',
                ],
                'analytics' => [
                    'connection' => 'pgsql_analytics',
                    'label' => 'Analytics',
                    'description' => 'Analytics database.',
                ],
            ]);

            $registry = new ConnectionRegistry;

            expect($registry->getConnectionNames())->toBe(['crm', 'analytics']);
        });
    });

    describe('getConnection', function () {
        it('returns ConnectionConfig for valid name', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'mysql_sales',
                    'label' => 'Sales DB',
                    'description' => 'Orders and products.',
                    'allowed_tables' => ['orders'],
                    'denied_tables' => ['audit'],
                    'hidden_columns' => ['users' => ['password']],
                ],
            ]);

            $registry = new ConnectionRegistry;
            $config = $registry->getConnection('sales');

            expect($config)->toBeInstanceOf(ConnectionConfig::class);
            expect($config->name)->toBe('sales');
            expect($config->connection)->toBe('mysql_sales');
            expect($config->label)->toBe('Sales DB');
            expect($config->description)->toBe('Orders and products.');
            expect($config->allowedTables)->toBe(['orders']);
            expect($config->deniedTables)->toBe(['audit']);
            expect($config->hiddenColumns)->toBe(['users' => ['password']]);
        });

        it('throws for unknown name', function () {
            config()->set('sql-agent.database.connections', []);

            $registry = new ConnectionRegistry;

            expect(fn () => $registry->getConnection('unknown'))
                ->toThrow(InvalidArgumentException::class, 'Unknown connection');
        });

        it('uses defaults for optional config fields', function () {
            config()->set('sql-agent.database.connections', [
                'minimal' => [
                    'connection' => 'sqlite',
                ],
            ]);

            $registry = new ConnectionRegistry;
            $config = $registry->getConnection('minimal');

            expect($config->label)->toBe('minimal');
            expect($config->description)->toBe('');
            expect($config->allowedTables)->toBe([]);
            expect($config->deniedTables)->toBe([]);
            expect($config->hiddenColumns)->toBe([]);
        });
    });

    describe('getLaravelConnection', function () {
        it('maps logical name to Laravel connection', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'mysql_sales',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                ],
            ]);

            $registry = new ConnectionRegistry;

            expect($registry->getLaravelConnection('sales'))->toBe('mysql_sales');
        });
    });

    describe('resolveConnection', function () {
        it('resolves logical name to Laravel connection', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'mysql_sales',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                ],
            ]);

            $registry = new ConnectionRegistry;

            expect($registry->resolveConnection('sales'))->toBe('mysql_sales');
        });

        it('returns null when no name provided', function () {
            config()->set('sql-agent.database.connections', [
                'sales' => [
                    'connection' => 'mysql_sales',
                    'label' => 'Sales',
                    'description' => 'Sales database.',
                ],
            ]);

            $registry = new ConnectionRegistry;

            expect($registry->resolveConnection(null))->toBeNull();
        });
    });

    describe('all', function () {
        it('returns all connection configs', function () {
            config()->set('sql-agent.database.connections', [
                'a' => ['connection' => 'conn_a', 'label' => 'A', 'description' => 'A db.'],
                'b' => ['connection' => 'conn_b', 'label' => 'B', 'description' => 'B db.'],
            ]);

            $registry = new ConnectionRegistry;
            $all = $registry->all();

            expect($all)->toHaveCount(2);
            expect($all)->toHaveKeys(['a', 'b']);
            expect($all['a'])->toBeInstanceOf(ConnectionConfig::class);
            expect($all['b'])->toBeInstanceOf(ConnectionConfig::class);
        });

        it('caches parsed connections', function () {
            config()->set('sql-agent.database.connections', [
                'x' => ['connection' => 'conn_x', 'label' => 'X', 'description' => 'X db.'],
            ]);

            $registry = new ConnectionRegistry;

            $first = $registry->all();
            $second = $registry->all();

            expect($first)->toBe($second);
        });
    });
});
