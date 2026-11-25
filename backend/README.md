# Auth API

ThinkPHP 8 / PHP 8.2 API foundation.

## Local setup

1. Install PHP 8.2+, Composer, MySQL and Redis.
2. Copy `.env.example` to `.env` and set local credentials.
3. Run `composer install`.
4. Start the ThinkPHP development server from this directory: `php think run`.
5. Check `GET /api/health`.

No production secrets belong in this repository. Payment credentials and signing keys must be supplied through a secret manager in deployed environments.