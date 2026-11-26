# Software API RSA-PSS request authentication

All client-facing license runtime endpoints require an RSA-PSS signature. The software App ID is an identifier, not a secret. A client must keep its RSA private key private and upload only the matching public PEM to the platform.

## Key generation

Generate a 3072-bit RSA key pair locally:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkey -in client_private.pem -pubout -out client_public.pem
```

Upload `client_public.pem` in the software's **客户端 RSA-PSS 签名公钥** section. Keep `client_private.pem` only in the client build's protected secret store. Never upload or commit the private key.

Up to two public keys can remain active simultaneously. Add the replacement key to clients, migrate traffic, then revoke the old key. Revocation takes effect immediately.

## Required headers

- `X-App-Id`: software App ID.
- `X-Key-Id`: key ID returned after public key upload.
- `X-Timestamp`: Unix timestamp in seconds; maximum accepted clock skew is 300 seconds.
- `X-Nonce`: unique 16–64 character value. A nonce cannot be reused for the same App ID and Key ID.
- `X-Signature`: Base64-encoded RSA-PSS signature.

## Canonical message

The exact request body bytes must be hashed. Do not parse and re-serialize JSON after signing.

```text
UPPERCASE_HTTP_METHOD
/normalized/request/path
UNIX_TIMESTAMP
NONCE
LOWERCASE_HEX_SHA256_OF_EXACT_BODY_BYTES
```

The lines are joined with a single LF byte (`\n`) and no trailing newline. The normalized path starts with `/` and excludes the query string.

Sign the UTF-8 canonical message using:

- RSA-PSS
- Message digest: SHA-256
- MGF1 digest: SHA-256
- Salt length: 32 bytes

The server verifies the signature before inserting the nonce. A repeated nonce returns HTTP 409. Invalid signatures, unknown/revoked keys, disabled software, and stale timestamps are rejected before the license handler runs.

## Runtime endpoints

- `POST /api/license/activate`
- `POST /api/license/verify`
- `POST /api/license/change-device`
- `POST /api/license/unbind`

All four endpoints require the headers above. Use HTTPS in every environment outside isolated local development. Do not log license keys, signatures, or private key material.

## Operational notes

Nonce rows older than ten minutes are periodically pruned by valid requests. The current quality workflow runs PHP syntax checks and unit tests but does not provision a MySQL integration environment; unique-index replay protection and transaction behavior must still be verified against the deployment MySQL version.
