# Auth API

ThinkPHP 8 / PHP 8.2 API foundation.

## Local setup

1. Install PHP 8.2+, Composer, MySQL and Redis.
2. Copy `.env.example` to `.env` and set local credentials.
3. Run `composer install`.
4. Start the ThinkPHP development server from this directory: `php think run`.
5. Check `GET /api/health`.

No production secrets belong in this repository. Payment credentials and signing keys must be supplied through a secret manager in deployed environments.

## Payment foundation

- Apply the regional payment schema once: `mysql --default-character-set=utf8mb4 -u <user> -p <database> < database/schema/001_payment_core.sql`.
- Set `APP_KEY` to a random secret of at least 32 characters in the deployment secret store. Never commit the real value.
- Payment merchant credentials are stored as AES-256-GCM ciphertext. The callback controller decrypts them server-side only.
- Register a channel row in `auth_payment_channels` only after the corresponding merchant contract and callback URL are configured. `credentials_ciphertext` must be produced by `PaymentCredentialCipher::encrypt(json_encode($credentials))`; the credentials JSON must contain the provider secret key. The `configuration` JSON contains non-secret endpoint, callback, and checkout allow-list settings.
- Provider callbacks use `POST|GET /api/payments/callback/{channel}`. GMPay/EPUSDT acknowledge a committed callback with plain text `ok`; EPay uses plain text `success`.
- A callback only credits the platform amount and currency from the immutable local payment order. Provider amount/currency are checked against the provider snapshot; `actual_amount` is never credited.
- Wallet account rows for both currencies must be initialized when a user wallet is created. Missing wallet rows intentionally cause callback rejection and provider retry rather than silently creating an untracked account.

The payment schema and callback endpoint are a backend foundation. Channel configuration management, payment order creation, customer cashier UI, reconciliation workers, and live merchant verification remain separate implementation steps.
