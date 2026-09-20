# Corvant

[![Tests](https://github.com/aleexvdev/corvant-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/aleexvdev/corvant-laravel/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11.44%2B%20%7C%2012-ff2d20)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Identity & Access Management for Laravel, embedded — not hosted.**

Corvant is a Composer package that adds authentication, MFA, RBAC, multi-tenancy, sessions,
audit logging and security policies to a Laravel application. It installs as a dependency and
runs inside your app's own process — it is **not** a hosted identity provider like Auth0 or
Okta, and it never talks OAuth2/OIDC to your app over the network. There is nothing to deploy,
nothing external to trust, and no vendor lock-in beyond `composer.json`.

## Why embedded instead of hosted

Most IAM products assume you're willing to hand identity to a third-party service and integrate
against it. Corvant assumes the opposite: your app already has a Laravel process, a database and
Redis — identity infrastructure belongs there, under your own migrations, your own tests, your
own deploy pipeline. You get the same building blocks (MFA, RBAC, audit trails) without adding a
network dependency to your login flow.

## Architecture

Corvant follows **Hexagonal Architecture (Ports & Adapters)**. The domain layer
(`src/Domain/`) contains all business logic and has zero dependency on Laravel or Eloquent —
every interaction with persistence, cache, mail or MFA providers crosses through a `Port`
interface, implemented by a swappable `Adapter`. This is why, for example, password hashing goes
through `PasswordHasherPort` instead of calling Laravel's `Hash` facade directly from domain code:
you could replace `LaravelHasher` with a different implementation without touching a single
business rule.

```
src/
├── Domain/          business logic — Entities, Services, ValueObjects. No Illuminate imports.
├── Ports/            interfaces the domain depends on (UserRepositoryPort, SessionStorePort, …)
├── Adapters/         concrete implementations (Eloquent, Redis, Laravel Mail, Google2FA, …)
└── Infrastructure/    the Laravel glue — controllers, middleware, routes, the ServiceProvider
```

## Modules

| Module | What it does |
|---|---|
| **Authentication** | Register, login, logout, token refresh, password reset, email verification — opaque server-side sessions (Redis), not stateless JWTs. |
| **MFA** | TOTP (Google Authenticator–compatible) with a two-step enable/confirm flow, plus single-use recovery codes for lost devices. |
| **Sessions** | List and revoke your own active sessions, individually or all-but-current — without ever exposing a raw bearer token in a response. |
| **RBAC** | Roles and permissions scoped per tenant, with a route middleware (`permission:invoice:create`) and a built-in cross-tenant `SuperAdmin`. |
| **Multi-tenancy** | Tenant resolution via request header, automatic tenant provisioning with structural roles (`Owner`, `Member`, `Auditor`, `Guest`) seeded on creation. |
| **Users & Profile** | Self-service profile management — name/avatar/locale/timezone, email change with re-verification, password change, account deletion. |
| **Audit Log** | Automatic, queryable logging of the security events that matter: logins, logouts, password/role/MFA changes, session revocations. |
| **Security Policies** | Configurable password complexity, login rate limiting, account lockout after repeated failures, and session expiration after inactivity. |

## Requirements

- PHP ^8.2
- Laravel 11.44+ or 12.x
- MySQL 8 or PostgreSQL 16 (portable across both — no engine-specific SQL anywhere in the package)
- Redis (sessions, MFA challenges, rate limiting, single-use tokens)

## Installation

```bash
composer require alexvdev/corvant
php artisan vendor:publish --tag=corvant-migrations
php artisan migrate
php artisan vendor:publish --tag=corvant-config
```

Corvant creates and owns its own tables (`corvant_users`, `corvant_tenants`, `corvant_roles`, …)
— it never assumes your application's existing `users` schema. Publishing the config gives you
`config/corvant.php`, where every policy below is tunable via `.env`.

## Configuration reference

All keys are read from `config/corvant.php`, each backed by an `env()` default:

| Key | Env var | Default | What it controls |
|---|---|---|---|
| `password.min_length` | `CORVANT_PASSWORD_MIN_LENGTH` | `8` | Minimum password length. |
| `password.require_mixed_case` | `CORVANT_PASSWORD_REQUIRE_MIXED_CASE` | `true` | Require upper + lower case. |
| `password.require_numbers` | `CORVANT_PASSWORD_REQUIRE_NUMBERS` | `true` | Require at least one digit. |
| `password.require_symbols` | `CORVANT_PASSWORD_REQUIRE_SYMBOLS` | `true` | Require at least one symbol. |
| `session.ttl_seconds` | `CORVANT_SESSION_TTL_SECONDS` | `3600` | Idle timeout — each authenticated request slides the session's TTL back to this value. |
| `rate_limiting.login_attempts_per_minute` | `CORVANT_LOGIN_ATTEMPTS_PER_MINUTE` | `5` | Requests per minute to `/auth/login`, keyed by email + IP. |
| `account_lockout.max_failed_attempts` | `CORVANT_ACCOUNT_LOCKOUT_MAX_FAILED_ATTEMPTS` | `5` | Failed logins before an account locks. |
| `account_lockout.lockout_duration_seconds` | `CORVANT_ACCOUNT_LOCKOUT_DURATION_SECONDS` | `900` | How long a lockout lasts. |
| `password_reset.ttl_seconds` | `CORVANT_PASSWORD_RESET_TTL_SECONDS` | `3600` | Password reset token lifetime. |
| `email_verification.ttl_seconds` | `CORVANT_EMAIL_VERIFICATION_TTL_SECONDS` | `86400` | Email verification token lifetime. |
| `email_change.ttl_seconds` | `CORVANT_EMAIL_CHANGE_TTL_SECONDS` | `86400` | Email-change confirmation token lifetime. |
| `tenancy.header` | `CORVANT_TENANT_HEADER` | `X-Tenant-ID` | Header used to resolve the current tenant. |
| `mfa.challenge_ttl_seconds` | `CORVANT_MFA_CHALLENGE_TTL_SECONDS` | `300` | How long a pending MFA login challenge stays valid. |
| `mfa.recovery_codes_count` | `CORVANT_MFA_RECOVERY_CODES_COUNT` | `10` | Recovery codes generated per batch. |
| `mfa.issuer` | `CORVANT_MFA_ISSUER` | `Corvant` | Issuer name shown in authenticator apps. |
| `notifications.password_reset_url` | `CORVANT_PASSWORD_RESET_URL` | _(none)_ | Your frontend's reset URL, with a `{token}` placeholder. |
| `notifications.email_verification_url` | `CORVANT_EMAIL_VERIFICATION_URL` | _(none)_ | Same, for email verification. |
| `notifications.email_change_confirmation_url` | `CORVANT_EMAIL_CHANGE_CONFIRMATION_URL` | _(none)_ | Same, for email-change confirmation. |

The three `notifications.*` URLs are optional — Corvant ships no frontend of its own. Leave them
unset and transactional emails show the raw token instead of a button; set them once your app has
a page to receive that token.

## API surface

All routes are prefixed by your application's own API routing (Corvant registers them via
`loadRoutesFrom`, unprefixed — mount them under `/api` or wherever your app's routes live).

```
POST   /auth/register
POST   /auth/login                          → 200 with a session, or 200 {mfa_required, challenge_token} if MFA is enabled
POST   /auth/logout
POST   /auth/refresh
POST   /auth/forgot-password
POST   /auth/reset-password
POST   /auth/verify-email
POST   /auth/resend-verification            (auth required)

POST   /mfa/totp/enable                     (auth required)
POST   /mfa/totp/confirm                    (auth required)
POST   /mfa/totp/disable                    (auth required)
POST   /mfa/totp/verify                     (completes a login started with an MFA challenge)
GET    /mfa/recovery-codes                  (auth required)
POST   /mfa/recovery-codes/use              (alternate to totp/verify)

GET    /sessions                            (auth required)
DELETE /sessions/{id}                       (auth required)
DELETE /sessions                            (auth required — revokes all but the current one)

GET    /roles
POST   /roles
PUT    /roles/{id}
DELETE /roles/{id}
GET    /permissions
POST   /users/{userId}/roles
DELETE /users/{userId}/roles/{roleId}

POST   /tenants                             (auth required — seeds Owner/Member/Auditor/Guest)
GET    /tenants/current                     (resolved via the X-Tenant-ID header)
GET    /users/{userId}/tenants

GET    /users/me                            (auth required)
PUT    /users/me                            (auth required)
PUT    /users/me/email                      (auth required)
POST   /users/me/email/confirm              (auth required)
PUT    /users/me/phone                      (auth required)
PUT    /users/me/password                   (auth required)
DELETE /users/me                            (auth required)
GET    /users/me/audit                      (auth required)
```

"Auth required" means the `corvant.authenticate` middleware, which resolves the current user from
a `Bearer` token. RBAC-protected routes additionally support a `permission:<name>` middleware,
e.g. `Route::middleware('permission:invoice:create')`.

## Development

This repo runs entirely through Docker — no local PHP/Composer installation required.

```bash
docker compose build
docker compose run --rm app composer install
docker compose up -d mysql pgsql redis
docker compose run --rm app vendor/bin/pest
```

CI runs the full suite against every combination of PHP 8.2/8.3 and MySQL/PostgreSQL on every
push and pull request — see [`.github/workflows/tests.yml`](.github/workflows/tests.yml).

## Security notes

- Passwords are hashed via Laravel's `Hash` facade (bcrypt by default) behind `PasswordHasherPort`.
- Sessions are opaque 256-bit random tokens stored server-side in Redis — never a stateless JWT,
  never returned in a session-listing response, only at creation time.
- Anti-enumeration is a deliberate design constraint throughout: `forgot-password` returns an
  identical response whether or not the email is registered, and account lockout is checked
  before the user lookup even happens.
- Found a security issue? Please open a private report rather than a public issue.

## Roadmap

Laravel v1 (this repo) is feature-complete. A conceptually-equivalent but independently
implemented **NestJS** package (`@corvant/nestjs`) is a possible future phase — it would not
share code with this package, only the underlying design.

## License

MIT. See [LICENSE](LICENSE).
