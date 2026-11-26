# Auth License SDK for JavaScript / TypeScript

Node.js 18+ SDK written in TypeScript. It uses Node's built-in crypto and fetch APIs to sign every request with RSA-PSS / SHA-256, MGF1-SHA256, and a 32-byte salt. The compiled JavaScript output is consumable from both JavaScript and TypeScript projects.

## Install

From this repository:

```bash
cd sdk/typescript
npm install
npm run build
```

## Generate and register a key

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkey -in client_private.pem -pubout -out client_public.pem
```

Upload only the public PEM. Keep the private key in a protected secret store and never ship it in browser code or public web bundles.

## TypeScript usage

```ts
import { readFile } from "node:fs/promises";
import { LicenseClient } from "@auth/license-sdk";

const privateKeyPem = await readFile("/secure/path/client_private.pem", "utf8");
const client = new LicenseClient({
  baseUrl: "https://license.example.com",
  appId: "app_0123456789abcdef01234567",
  keyId: "key_0123456789abcdef",
  privateKeyPem,
});

const result = await client.verify(
  "01234567-89ABCDEF-01234567-89ABCDEF",
  "stable-device-fingerprint",
);
if (!result.valid) {
  // Apply the application's license policy.
}
```

## Operations

- `activate(licenseKey, deviceFingerprint, deviceLabel?)`
- `verify(licenseKey, deviceFingerprint)`
- `changeDevice(licenseKey, currentFingerprint, newFingerprint, deviceLabel?)`
- `unbind(licenseKey, deviceFingerprint)`

An optional `fetchImplementation` can be injected for custom transports and tests. Requests time out after 10 seconds by default. API rejections throw `LicenseApiError` with the HTTP status code.
