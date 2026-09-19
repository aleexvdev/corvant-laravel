<?php

declare(strict_types=1);

namespace Corvant\Infrastructure;

use Corvant\Adapters\Persistence\EloquentUserRepository;
use Corvant\Adapters\Security\LaravelHasher;
use Corvant\Adapters\Session\RedisSessionStore;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\UserRepositoryPort;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\ServiceProvider;

class CorvantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/corvant.php', 'corvant');

        $this->app->bind(PasswordHasherPort::class, LaravelHasher::class);
        $this->app->bind(UserRepositoryPort::class, EloquentUserRepository::class);

        $this->app->bind(SessionStorePort::class, function ($app): RedisSessionStore {
            return new RedisSessionStore(
                $app->make(RedisFactory::class),
                (int) $app['config']->get('corvant.session.ttl_seconds', 3600),
            );
        });

        $this->app->singleton(AuthenticationService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->publishes([
            __DIR__.'/../../config/corvant.php' => config_path('corvant.php'),
        ], 'corvant-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'corvant-migrations');
    }
}
