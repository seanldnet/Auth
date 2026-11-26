# Auth License SDK for Go

Go client for the Auth license runtime API. It uses only the Go standard library and signs each request with RSA-PSS / SHA-256, MGF1-SHA256, and a 32-byte salt.

## Key generation

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out client_private.pem
openssl pkey -in client_private.pem -pubout -out client_public.pem
```

Upload only `client_public.pem` to the software's RSA-PSS signing key section. Keep the private key in a protected secret store.

## Usage

```go
package main

import (
    "context"
    "fmt"
    "os"

    authlicensesdk "github.com/seanldnet/auth-license-sdk"
)

func main() {
    privateKey, err := os.ReadFile("/secure/path/client_private.pem")
    if err != nil { panic(err) }

    client, err := authlicensesdk.NewClient(
        "https://license.example.com",
        "app_0123456789abcdef01234567",
        "key_0123456789abcdef",
        privateKey,
        nil,
    )
    if err != nil { panic(err) }

    result, err := client.Verify(context.Background(),
        "01234567-89ABCDEF-01234567-89ABCDEF",
        "stable-device-fingerprint",
    )
    if err != nil { panic(err) }
    fmt.Println("valid:", result.Valid)
}
```

## Operations

- `Activate(ctx, licenseKey, fingerprint, deviceLabel)`
- `Verify(ctx, licenseKey, fingerprint)`
- `ChangeDevice(ctx, licenseKey, currentFingerprint, newFingerprint, deviceLabel)`
- `Unbind(ctx, licenseKey, fingerprint)`

Pass a custom `*http.Client` to `NewClient` to configure transport, proxy, TLS, or timeouts. If nil, a client with a 10-second timeout is created.
