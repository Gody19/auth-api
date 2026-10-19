# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-10-19

### Added

- Initial release of the standalone Authentication API (Laravel 12, PHP 8.2, MySQL, Sanctum).
- `POST /api/v1/auth/register` — account creation with validation, unique-email enforcement, password policy (min 8, letters + numbers), bcrypt hashing, and immediate access-token issuance.
- `POST /api/v1/auth/login` — credential authentication with generic error messages and per-account/IP rate limiting; issues bearer tokens with configurable expiry.
- `GET /api/v1/auth/me` — token-authenticated profile retrieval via API Resource.
- `POST /api/v1/auth/logout` — revokes the current access token.
- `POST /api/v1/auth/forgot-password` / `POST /api/v1/auth/reset-password` — Laravel password-broker flow with expiring single-use tokens; all user tokens revoked on reset; account-existence neutral responses.
- `GET /api/v1/auth/verify-email/{id}/{hash}` — signed-URL email verification (Laravel's built-in mechanism).
- `POST /api/v1/auth/resend-verification` — authenticated resend of the verification email, throttled.
- Consistent JSON response envelope (`success`, `message`, `data`, `errors`) and centralized API exception rendering (401/403/404/422/429).
- Configurable token lifetime via `SANCTUM_EXPIRATION` (default 30 days).
- Configurable password-reset email link via `PASSWORD_RESET_URL`.
- 53 automated feature tests covering all endpoints, error modes, token revocation, and rate limits (in-memory SQLite).
- OpenAPI 3.0 specification (`docs/openapi.yaml`), README, MIT license, contributing guide, and GitHub Actions CI workflow.
