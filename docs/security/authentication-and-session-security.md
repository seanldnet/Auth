# Authentication, Sessions and RBAC

## 1. Identity model

The initial account roles are:
- `ADMIN`: global platform administration.
- `AGENT`: manages only users and authorization resources within the assigned agency scope.
- `SUB_AGENT`: manages only its permitted descendant scope.
- `USER`: self-service account.

Role checks are enforced by backend middleware and service-level ownership checks. Hiding a front-end menu is never an authorization boundary.

## 2. Passwords and first administrator

- Password hashes use Argon2id when available, otherwise PHP's configured secure default.
- The first administrator is created only by `backend/bin/create-admin.php`.
- The bootstrap command reads `AUTH_BOOTSTRAP_ADMIN_USERNAME` and `AUTH_BOOTSTRAP_ADMIN_PASSWORD` from the process environment; the initial password must contain at least 16 characters.
- Bootstrap refuses to run if the user table is non-empty and creates separate CNY and USDT wallet rows atomically.
- The initial account must change its password on first login. New passwords must contain at least 12 characters.
- No default administrator account or password is committed to the repository.

## 3. Session model

- Login issues a 256-bit random opaque session token.
- Only SHA-256(token) is stored in `auth_user_sessions`.
- Browser sessions use an HttpOnly, SameSite=Lax cookie; the token is not exposed to JavaScript or localStorage.
- Session lifetime is 12 hours. Logout revokes the server-side session.
- Password change revokes all other active sessions for that account.
- Middleware checks account status and session expiry on every protected request.
- A bearer-token header is accepted for trusted non-browser API clients; browser sessions use the HttpOnly cookie.

## 4. Login abuse and audit

- Failed attempts are tracked by hashes of username and source IP, with both per-account and per-IP aggregate counters.
- Five failed attempts in a 15-minute window trigger a 15-minute lock for the account or source IP scope.
- Login success/failure, logout, password changes, and payment-channel configuration changes are written to the audit table.
- Passwords, raw session tokens and merchant secrets are never written to audit metadata.

## 5. CSRF and transport

- Session cookies are HttpOnly and SameSite=Lax.
- Mutating requests with cookie authentication validate the Origin host against the request host when an Origin header is present.
- Production deployments must use HTTPS so the session cookie is marked Secure.
- Reverse proxies must preserve the original Host header and must not trust arbitrary forwarded-host headers.
- API clients using bearer tokens are not subject to cookie-based CSRF checks.

## 6. RBAC enforcement

- `AuthMiddleware` validates the session and binds the authenticated principal to the current request.
- `AdminMiddleware` protects administrator-only endpoints.
- Payment-channel configuration is administrator-only.
- Payment order creation requires an authenticated user; public cashier reads require the per-order 256-bit checkout token.
- Agency hierarchy and resource-level ownership policies must be applied in service queries before implementing agent/sub-agent business endpoints.

## 7. Current implementation boundary

Implemented: password login, opaque session issuance/revocation, current-user endpoint, first-login password rotation, login throttling, audit events, administrator route middleware, and authenticated payment-order creation.

Not yet implemented: registration/invitation flows, password reset, MFA/TOTP, session/device management UI, full agent/sub-agent hierarchy policies, granular permission editor, and account recovery procedures.
