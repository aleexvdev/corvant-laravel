<?php

declare(strict_types=1);

namespace Corvant\Infrastructure;

use Corvant\Adapters\Http\HeaderTenantResolver;
use Corvant\Adapters\Persistence\EloquentRoleRepository;
use Corvant\Adapters\Persistence\EloquentTenantRepository;
use Corvant\Adapters\Persistence\EloquentUserRepository;
use Corvant\Adapters\Mfa\TotpProvider;
use Corvant\Adapters\Notification\LaravelMailNotifier;
use Corvant\Adapters\Persistence\EloquentMfaRecoveryCodeRepository;
use Corvant\Adapters\Security\LaravelHasher;
use Corvant\Adapters\Session\RedisMfaChallengeStore;
use Corvant\Adapters\Session\RedisSessionStore;
use Corvant\Adapters\Session\RedisSingleUseTokenStore;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Domain\Mfa\Services\MfaService;
use Corvant\Domain\Rbac\Services\PermissionResolver;
use Corvant\Domain\Rbac\Services\RoleService;
use Corvant\Domain\Tenancy\Services\TenancyService;
use Corvant\Domain\Tenancy\Services\TenantProvisioningService;
use Corvant\Infrastructure\Authentication\CurrentUser;
use Corvant\Infrastructure\Http\Middleware\AuthenticateSessionMiddleware;
use Corvant\Infrastructure\Http\Middleware\PermissionMiddleware;
use Corvant\Infrastructure\Http\Middleware\ResolveTenantMiddleware;
use Corvant\Infrastructure\Tenancy\CurrentTenant;
use Corvant\Ports\MfaChallengePort;
use Corvant\Ports\MfaProviderPort;
use Corvant\Ports\MfaRecoveryCodePort;
use Corvant\Ports\NotificationPort;
use Corvant\Ports\PasswordHasherPort;
use Corvant\Ports\RoleRepositoryPort;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\SingleUseTokenPort;
use Corvant\Ports\TenantRepositoryPort;
use Corvant\Ports\TenantResolverPort;
use Corvant\Ports\UserRepositoryPort;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;

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

        $this->app->bind(SingleUseTokenPort::class, RedisSingleUseTokenStore::class);
        $this->app->bind(NotificationPort::class, LaravelMailNotifier::class);

        $this->app->bind(MfaProviderPort::class, function ($app): TotpProvider {
            return new TotpProvider(
                $app->make(Google2FA::class),
                (string) $app['config']->get('corvant.mfa.issuer', 'Corvant'),
            );
        });

        $this->app->bind(MfaChallengePort::class, function ($app): RedisMfaChallengeStore {
            return new RedisMfaChallengeStore(
                $app->make(RedisFactory::class),
                (int) $app['config']->get('corvant.mfa.challenge_ttl_seconds', 300),
            );
        });

        $this->app->bind(MfaRecoveryCodePort::class, EloquentMfaRecoveryCodeRepository::class);

        $this->app->singleton(AuthenticationService::class, function ($app): AuthenticationService {
            return new AuthenticationService(
                $app->make(UserRepositoryPort::class),
                $app->make(SessionStorePort::class),
                $app->make(MfaChallengePort::class),
                $app->make(PasswordHasherPort::class),
                $app->make(SingleUseTokenPort::class),
                $app->make(NotificationPort::class),
                (int) $app['config']->get('corvant.password_reset.ttl_seconds', 3600),
                (int) $app['config']->get('corvant.email_verification.ttl_seconds', 86400),
                (int) $app['config']->get('corvant.email_change.ttl_seconds', 86400),
            );
        });

        $this->app->bind(TenantRepositoryPort::class, EloquentTenantRepository::class);

        $this->app->bind(TenantResolverPort::class, function ($app): HeaderTenantResolver {
            return new HeaderTenantResolver(
                (string) $app['config']->get('corvant.tenancy.header', 'X-Tenant-ID'),
            );
        });

        $this->app->scoped(CurrentTenant::class);
        $this->app->scoped(CurrentUser::class);

        $this->app->singleton(TenancyService::class);
        $this->app->singleton(TenantProvisioningService::class);

        $this->app->bind(RoleRepositoryPort::class, EloquentRoleRepository::class);
        $this->app->singleton(PermissionResolver::class);
        $this->app->singleton(RoleService::class);

        $this->app->singleton(MfaService::class, function ($app): MfaService {
            return new MfaService(
                $app->make(UserRepositoryPort::class),
                $app->make(MfaProviderPort::class),
                $app->make(MfaChallengePort::class),
                $app->make(SessionStorePort::class),
                $app->make(PasswordHasherPort::class),
                $app->make(MfaRecoveryCodePort::class),
                (int) $app['config']->get('corvant.mfa.recovery_codes_count', 10),
            );
        });
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
