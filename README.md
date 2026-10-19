# Auth API

A standalone, open-source Authentication API built with Laravel 12. It gives any application — built in PHP, Java, Python, JavaScript, Flutter, or anything else that can make HTTP requests — secure user registration, login, token management, password recovery, and email verification without implementing authentication from scratch.

This is the first service in a planned ecosystem of independent, reusable APIs. Each service is a self-contained Laravel application with its own database and configuration, consumed over versioned REST endpoints (`/api/v1/...`) with JSON request and response bodies.

## Features

- **Registration** — validated input, unique emails, strong password policy, bcrypt hashing, instant access token.
- **Login** — secure credential verification, generic failure messages (never reveals whether an email exists), per-account rate limiting.
- **Current user** — `GET /api/v1/auth/me` for token-authenticated profile retrieval.
- **Logout** — revokes the current access token immediately.
- **Password recovery** — expiring, single-use reset tokens via Laravel's password broker; all access tokens are revoked on successful reset.
- **Email verification** — Laravel's built-in signed-URL verification flow with a resend endpoint.
- **Token lifecycle** — configurable expiration (default 30 days), revocation on logout and password reset.
- **Consistent JSON envelope** — every response uses a predictable `{success, message, data, errors}` structure.
- **Safe serialization** — responses are built with API Resources; password hashes and token hashes are never exposed.
- **Automated tests** — 53 PHPUnit feature tests covering every endpoint, failure mode, and rate limit.

## Requirements

- PHP 8.2+ (with `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `xml`, `curl` extensions)
- Composer 2
- MySQL 8+ / MariaDB 10.4+
- Laravel 12 (installed via Composer)

## Installation

### 1. Clone and install dependencies

```bash
git clone https://github.com/gody19/auth-api.git
cd auth-api
composer install
```

### 2. Create the database

**Windows (XAMPP):**

1. Start **MySQL** from the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`.
3. Create a new database named `auth_api` with **utf8mb4_unicode_ci** collation.

**Linux (MySQL CLI):**

```sql
CREATE DATABASE auth_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auth_api
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

Key configuration values:

| Variable | Purpose | Default |
|---|---|---|
| `APP_URL` | Base URL of this API. Must match the URL clients use — it is part of signed verification URLs. | `http://localhost:8000` |
| `SANCTUM_EXPIRATION` | Access-token lifetime in minutes. | `43200` (30 days) |
| `PASSWORD_RESET_URL` | Base URL used in password-reset emails. Point it at your client app's reset page. | `${APP_URL}` |
| `MAIL_MAILER` | Mail transport. Use `log` locally to write emails to `storage/logs/laravel.log`. | `log` |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | SMTP settings for real email delivery. | — |

### 4. Run migrations (and optionally seed a demo user)

```bash
php artisan migrate
php artisan db:seed   # optional: creates dev@example.com / password123
```

### 5. Start the development server

```bash
php artisan serve
```

The API is now available at `http://127.0.0.1:8000`.

## Quick start — try the endpoints

Replace the host/port with wherever `php artisan serve` is running.

```bash
# Register
curl -X POST http://127.0.0.1:8000/api/v1/auth/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane","email":"jane@example.com","password":"Password123","password_confirmation":"Password123"}'

# Login
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"jane@example.com","password":"Password123"}'

# Current user (use the access_token from the login response)
curl http://127.0.0.1:8000/api/v1/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"

# Logout
curl -X POST http://127.0.0.1:8000/api/v1/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

You can also import the endpoints into any REST client (Postman, Insomnia, Bruno) — every request above is plain HTTP + JSON.

## API endpoints

All endpoints are prefixed with `/api/v1`. Full request/response schemas are in [`docs/openapi.yaml`](docs/openapi.yaml).

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/api/v1/auth/register` | — | Create an account and receive an access token |
| `POST` | `/api/v1/auth/login` | — | Authenticate and receive an access token |
| `GET` | `/api/v1/auth/me` | Bearer | Get the authenticated user's public profile |
| `POST` | `/api/v1/auth/logout` | Bearer | Revoke the current access token |
| `POST` | `/api/v1/auth/forgot-password` | — | Request a password-reset email |
| `POST` | `/api/v1/auth/reset-password` | — | Reset a password with a valid broker token |
| `GET` | `/api/v1/auth/verify-email/{id}/{hash}?expires&signature` | Signed URL | Verify an email address |
| `POST` | `/api/v1/auth/resend-verification` | Bearer | Resend the verification email |

### Response format

Success:

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": { "id": 1, "name": "Jane", "email": "jane@example.com", "email_verified_at": null, "created_at": "2026-10-19T12:00:00+00:00" },
    "access_token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer",
    "expires_at": "2026-11-18T12:00:00+00:00"
  }
}
```

Error:

```json
{
  "success": false,
  "message": "The supplied credentials are invalid.",
  "errors": {}
}
```

Validation errors preserve Laravel's field-level details:

```json
{
  "success": false,
  "message": "The email field must be a valid email address.",
  "errors": { "email": ["The email field must be a valid email address."] }
}
```

Status codes: `200` OK · `201` Created · `202` Accepted · `401` Unauthenticated · `403` Forbidden · `404` Not found · `409` Conflict · `422` Validation error · `429` Rate limited.

### Authentication

Send the access token as a bearer token:

```
Authorization: Bearer <access_token>
```

### Password policy

Passwords must be at least 8 characters and contain at least one letter and one number. Enforced on registration and password reset via Laravel's `Password` rule.

### Rate limits

| Endpoint | Limit |
|---|---|
| `register` | 6 requests / minute / IP |
| `login` | 10 requests / minute / IP, plus 6 failed attempts / minute / email+IP |
| `forgot-password`, `reset-password`, `resend-verification`, `verify-email` | 6 requests / minute |

Rate-limited requests receive `429` with the standard error envelope.

### Email verification policy

`App\Models\User` implements `MustVerifyEmail`, and the verification/resend endpoints are fully functional. However, **verification is not required to use protected endpoints by default** — a newly registered user can call `GET /me` immediately. If you want to require verification, attach Laravel's `verified` middleware to the routes you want to protect:

```php
Route::get('/features', ...)->middleware(['auth:sanctum', 'verified']);
```

Password-reset and verification emails are **not** sent automatically on registration (this prevents email bombing). Clients call `POST /auth/resend-verification` or `POST /auth/forgot-password` explicitly.

## Running automated tests

Tests run against an isolated in-memory SQLite database — they never touch your MySQL data.

```bash
php artisan test
```

## Security considerations

- **Password storage** — passwords are hashed with bcrypt (Laravel's `hashed` cast). Hashes are never returned by any endpoint.
- **Token storage** — Sanctum stores only SHA-256 hashes of access tokens; the plaintext token is returned exactly once, at issuance.
- **Generic auth errors** — login, forgot-password, and reset-password responses never reveal whether an email is registered.
- **Rate limiting** — brute-force protection on login (per account and per IP) and abuse protection on all sensitive endpoints.
- **Token revocation** — logout revokes the current token; a successful password reset revokes **all** of the user's tokens.
- **Token expiration** — tokens expire after `SANCTUM_EXPIRATION` minutes (default 30 days). Expired tokens are rejected by Sanctum's guard.
- **Mass assignment** — the `User` model's fillable list contains only `name`, `email`, `password`. Clients cannot set `email_verified_at`, roles, or any other field.
- **Signed verification URLs** — email-verification links are HMAC-signed and expire (Laravel's `signed` middleware + `throttle`).
- **Safe serialization** — all output passes through API Resources; Eloquent models are never returned directly.
- **HTTPS in production** — always terminate TLS in front of this API. Bearer tokens in plaintext HTTP are trivially stolen. Set `APP_DEBUG=false` and a real `APP_URL` in production.
- **CORS** — Laravel's default CORS configuration allows all origins, which suits a public API. If only specific browser origins should call the API, tighten `config/cors.php` (publish it with `php artisan config:publish cors`).
- **Tokens vs cookies** — this API uses Sanctum *personal access tokens* (bearer tokens). That is the right choice for mobile/Flutter/native clients and third-party integrations. If you are building a first-party browser SPA, evaluate Sanctum's cookie-based SPA authentication instead — do not expose long-lived bearer tokens to browser JavaScript when cookies would do.

### Limitations of Sanctum personal access tokens

- Tokens are **not scoped** (no per-token abilities) and **not tied to an expiration timestamp per token** — expiry is global via `SANCTUM_EXPIRATION`.
- There is no built-in refresh-token flow; clients re-authenticate with credentials when a token expires.
- Revocation is immediate but requires a round trip (logout or password reset). If a token leaks before revocation, it is valid until expiry.

If you later need OAuth 2.0 (third-party authorization, scopes, consent screens), evaluate a standards-compliant server such as Laravel Passport as a separate decision — this service deliberately does not invent an authentication protocol.

## Production deployment

1. Use a Linux server with PHP 8.2+ (FPM) and a web server (Nginx/Apache) or use a managed Laravel host.
2. Serve over **HTTPS only** (Let's Encrypt or your provider's TLS).
3. Set `APP_ENV=production`, `APP_DEBUG=false`, and a strong `APP_URL` (https).
4. Set real SMTP credentials in `.env` (or use Postmark/SES/etc.) so password-reset and verification emails are delivered. `MAIL_MAILER=log` is for local development only.
5. Create a dedicated MySQL database and user with privileges limited to that database.
6. Run `php artisan migrate --force`.
7. Configure the web server to serve only the `public/` directory.
8. Back up the database regularly; `personal_access_tokens` and `password_reset_tokens` are the security-critical tables.

### Upgrades

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
```

## Project structure

```
app/
  Http/
    Controllers/Api/V1/    # AuthController, EmailVerificationController, base Controller
    Requests/Api/V1/       # Form Requests (validation)
    Resources/Api/V1/      # UserResource (output shaping)
  Models/User.php
  Services/AuthService.php # Business logic: registration, tokens, password resets
  Providers/AppServiceProvider.php
database/
  migrations/              # users, password_reset_tokens, personal_access_tokens
  factories/  seeders/
routes/api.php             # Versioned /api/v1 routes
tests/Feature/Api/V1/      # Endpoint test suites
docs/openapi.yaml          # OpenAPI 3.0 specification
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Short version: fork, create a feature branch, run `php artisan test` and `vendor/bin/pint`, open a pull request.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

[MIT](LICENSE).
