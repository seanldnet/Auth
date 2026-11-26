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

## Payment order and cashier flow

- Payment order creation is provided by `PaymentOrderService::create()`; call it only after the authenticated API layer has resolved the user ID.
- The caller supplies an idempotency key. Repeated requests with the same user and key return the same order instead of creating a second provider order.
- Platform amount and provider amount are identical. The service requires an explicit provider-currency mapping for each platform currency and never calculates an exchange rate.
- The user wallet must already have a separate row for the selected CNY or USDT currency.
- The returned `checkout_url` is the Auth cashier page. Its 256-bit token is stored only as a SHA-256 hash in the payment order.
- The public read endpoint is `GET /api/payments/cashier/{orderNo}/{token}`; it returns only safe order data and the validated provider cashier URL. It never returns merchant credentials.
- The React cashier page polls this endpoint every five seconds while the order is being created or is pending.
- An ambiguous provider creation timeout moves the local order to `REVIEW`; it is not blindly retried, avoiding duplicate charges.

The service and public cashier read endpoint are implemented. The authenticated order-creation controller and complete admin channel-configuration UI are intentionally gated on the platform authentication/RBAC layer; do not expose order creation without that layer.

## Authentication and role foundation

- Apply the auth schema after the payment schema: `mysql --default-character-set=utf8mb4 -u <user> -p <database> < database/schema/002_auth_core.sql`.
- There is no built-in administrator or default password. Bootstrap the first administrator with process environment variables:
  `AUTH_BOOTSTRAP_ADMIN_USERNAME=<admin-name> AUTH_BOOTSTRAP_ADMIN_PASSWORD=<random-16+-character-password> php bin/create-admin.php`.
- The bootstrap command refuses to run if any user already exists. It marks the first account as requiring a password change.
- Login is `POST /api/auth/login`; logout and current-user endpoints require the session middleware.
- Sessions use 256-bit random opaque tokens in HttpOnly, SameSite=Lax cookies. Only SHA-256 token hashes are stored in MySQL; sessions expire after 12 hours.
- Password hashes use Argon2id when available, otherwise PHP's default password algorithm. Login failures are throttled by both username and source IP; audit events are written without storing passwords or raw session tokens.
- First login must change the bootstrap password to a new password of at least 12 characters before accessing protected routes.
- The initial role set is ADMIN, AGENT, SUB_AGENT, USER. Admin-only API routes must use `AdminMiddleware`; do not rely on front-end visibility for authorization.

Payment order creation is now available at authenticated `POST /api/payments/orders`. The public cashier read endpoint remains protected by the per-order high-entropy checkout token.

## Payment channel administration

- `GET /api/admin/payment-channels` lists channel status and non-secret configuration; it never returns merchant Secret values.
- `PUT /api/admin/payment-channels/{channelCode}` is restricted to ADMIN sessions. Merchant secrets are encrypted with AES-256-GCM before persistence.
- Supported configurable channel codes are ALIPAY, WECHAT_PAY, EPAY, EPUSDT and GMPAY. Alipay/WeChat are visible but cannot be enabled until their official adapters are implemented.
- EPUSDT, GMPay and EPay require HTTPS API/callback URLs, a cashier hostname allow-list and an explicit provider-currency mapping for each enabled platform currency.
- EPay with no currency override must match the gateway's configured default currency. Enabling both platform currencies with different provider currencies requires an EPay merchant API version that explicitly supports the currency parameter.

## Authenticated payment order API

- `POST /api/payments/orders` requires an authenticated session and an `Idempotency-Key` header (or `idempotency_key` JSON field).
- Request JSON fields: `channel_code`, `currency` (`CNY` or `USDT`), `amount`, optional `payment_type`, and optional paired `token` + `network`.
- The provider currency is selected from the administrator's explicit `provider_currency_by_platform_currency` map. Provider amount is exactly the same numeric amount as the platform order amount; the service performs no exchange-rate conversion.
- The API returns an Auth `checkout_url`; the customer page then displays the validated provider cashier URL and polls the order status.
