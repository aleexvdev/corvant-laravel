# Corvant

Identity & Access Management library for Laravel — authentication, MFA, RBAC, multi-tenancy, sessions and audit log.

Corvant is designed to be embedded as a Composer dependency inside a Laravel application, not run as a hosted identity provider. It follows Hexagonal Architecture (Ports & Adapters): the domain layer has no dependency on Laravel or Eloquent, and every integration with persistence, cache, notifications or MFA providers goes through a port implemented by an adapter.

## Requirements

- PHP ^8.2
- Laravel 10.x or 11.x

## Installation

```bash
composer require alexvdev/corvant
```

## Status

Early scaffolding — no released version yet. No authentication, MFA, or RBAC functionality is implemented.

## License

MIT. See [LICENSE](LICENSE).
