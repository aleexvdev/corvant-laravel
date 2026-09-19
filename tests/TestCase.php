<?php

declare(strict_types=1);

namespace Corvant\Tests;

use Corvant\Infrastructure\CorvantServiceProvider;
use Illuminate\Redis\RedisServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            CorvantServiceProvider::class,
            RedisServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $connection = env('DB_CONNECTION', 'mysql');

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $app['config']->set('database.default', $connection);

        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'mysql'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'corvant_testing'),
            'username' => env('DB_USERNAME', 'corvant'),
            'password' => env('DB_PASSWORD', 'secret'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]);

        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'pgsql'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'corvant_testing'),
            'username' => env('DB_USERNAME', 'corvant'),
            'password' => env('DB_PASSWORD', 'secret'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);

        $app['config']->set('database.redis.client', 'phpredis');
        $app['config']->set('database.redis.default', [
            'host' => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD'),
            'port' => (int) env('REDIS_PORT', 6379),
            'database' => (int) env('REDIS_DB', 0),
        ]);

        $app['config']->set('corvant.password.min_length', 8);
        $app['config']->set('corvant.session.ttl_seconds', 3600);
    }
}
