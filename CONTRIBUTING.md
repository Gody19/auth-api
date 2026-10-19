# Contributing to Auth API

Thanks for your interest in contributing. This document explains how to set up a development environment, the conventions we follow, and how to submit changes.

## Development setup

Requirements: PHP 8.2+, Composer 2, MySQL 8+ / MariaDB 10.4+.

```bash
git clone https://github.com/your-org/auth-api.git
cd auth-api
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database (e.g. `auth_api`), update the `DB_*` variables in `.env`, then:

```bash
php artisan migrate
php artisan db:seed    # optional demo user: dev@example.com / password123
php artisan serve
```

## Running tests

Tests use an in-memory SQLite database configured in `phpunit.xml`. They never touch your MySQL data.

```bash
php artisan test
```

All tests must pass before a pull request can be merged. When you fix a bug, add a regression test that fails without your fix.

## Code style

We use [Laravel Pint](https://laravel.com/docs/pint) with Laravel's default preset:

```bash
vendor/bin/pint        # fix style
vendor/bin/pint --test # check style without changing files
```

Additional conventions:

- Follow standard Laravel conventions (structure, naming, dependency injection).
- Use Form Requests for input validation and API Resources for output shaping.
- Keep business logic in service classes when it is shared or non-trivial; do not create service classes that only proxy a single model call.
- Never return raw Eloquent models from controllers.
- All API responses must use the `{success, message, data, errors}` envelope.
- Passwords are sensitive: never log them, never serialize them.

## Testing guidelines

- Feature tests live in `tests/Feature/Api/V1/` and target the public HTTP contract, not internals.
- Cover the happy path **and** failure modes: validation errors, unauthenticated access, rate limiting, and revoked/expired credentials.
- Use `RefreshDatabase`; never point tests at a real database.

## Reporting issues

Open a GitHub issue with:

- The Laravel/PHP versions and database you are using.
- Steps to reproduce.
- Expected and actual behavior (include the JSON response body if relevant).
- Never include real credentials, tokens, or personal data in issues.

## Pull requests

1. Fork the repository and create a branch from `main` (e.g. `feature/add-token-list-endpoint`).
2. Make focused, single-purpose changes. Open separate PRs for unrelated fixes.
3. Add or update tests for any behavior change.
4. Update `docs/openapi.yaml` and the `README.md` endpoint reference when endpoints change.
5. Add an entry to `CHANGELOG.md` under an `Unreleased` heading.
6. Ensure `php artisan test` and `vendor/bin/pint --test` both pass.
7. Open the pull request with a clear description of the problem and the solution.

## Security

Please report security vulnerabilities privately (GitHub Security Advisals or the maintainers' contact) rather than opening a public issue.
