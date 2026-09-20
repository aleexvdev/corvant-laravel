<?php

declare(strict_types=1);

namespace Corvant\Infrastructure;

use Corvant\Adapters\Http\HeaderTenantResolver;
use Corvant\Adapters\Persistence\EloquentRoleRepository;
use Corvant\Adapters\Persistence\EloquentTenantRepository;
use Corvant\Adapters\Persistence\EloquentUserRepository;
use Corvant\Adapters\Security\LaravelHasher;
use Corvant\Adapters\Session\RedisSessionStore;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Domain\Rbac\Services\PermissionResolver;
use Corvant\Domain\Rbac\Services\RoleService;
use Corvant\Domain\Tenancy\Services\TenancyService;
use Corvant\Infrastructure\Authentication\CurrentUser;
use Corvant\Infrastructure\Http\Middleware\AuthenticateSessionMiddleware;
use Corvant\Infrastructure\Http\Middleware\PermissionMiddleware;
use Corvant\Infrastructure\Http\Middleware\ResolveTenantMiddleware;
use Corvant\Infrastructure\Tenancy\CurrentTenant;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\RoleRepositoryPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\TenantRepositoryPort;
use Corvant\Ports\TenantResolverPort;
use Corvant\Ports\UserRepositoryPort;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Routing\Router;
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

        $this->app->bind(TenantRepositoryPort::class, EloquentTenantRepository::class);

        $this->app->bind(TenantResolverPort::class, function ($app): HeaderTenantResolver {
            return new HeaderTenantResolver(
                (string) $app['config']->get('corvant.tenancy.header', 'X-Tenant-ID'),
            );
        });

        $this->app->scoped(CurrentTenant::class);
        $this->app->scoped(CurrentUser::class);

        $this->app->singleton(TenancyService::class);

        $this->app->bind(RoleRepositoryPort::class, EloquentRoleRepository::class);
        $this->app->singleton(PermissionResolver::class);
        $this->app->singleton(RoleService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('corvant.resolve-tenant', ResolveTenantMiddleware::class);
        $router->aliasMiddleware('corvant.authenticate', AuthenticateSessionMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->publishes([
            __DIR__.'/../../config/corvant.php' => config_path('corvant.php'),
        ], 'corvant-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'corvant-migrations');
    }
}
