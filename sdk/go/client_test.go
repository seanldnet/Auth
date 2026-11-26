package authlicensesdk

import (
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/pem"
	"testing"
)

func TestCanonicalMessageHashesExactBody(t *testing.T) {
	body := []byte(`{"app_id":"app_123"}`)
	message, err := BuildCanonicalMessage("post", "/api/license/verify", "1795700000", "0123456789abcdef0123456789abcdef", body)
	if err != nil { t.Fatal(err) }
	if string(message[:len("POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n")]) != "POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n" {
		t.Fatal("canonical prefix mismatch")
	}
	digest := sha256.Sum256(body)
	if string(message[len(message)-64:]) != fmt.Sprintf("%x", digest[:]) { t.Fatal("body hash mismatch") }
}

func TestRSAPSSSignAndVerify(t *testing.T) {
	privateKey, err := rsa.GenerateKey(rand.Reader, 3072)
	if err != nil { t.Fatal(err) }
	message := []byte("signed request")
	signature, err := Sign(privateKey, message)
	if err != nil { t.Fatal(err) }
	raw, err := base64.StdEncoding.DecodeString(signature)
	if err != nil { t.Fatal(err) }
	digest := sha256.Sum256(message)
	if err := rsa.VerifyPSS(&privateKey.PublicKey, crypto.SHA256, digest[:], raw, &rsa.PSSOptions{SaltLength: 32, Hash: crypto.SHA256}); err != nil {
		t.Fatalf("signature should verify: %v", err)
	}
	digest = sha256.Sum256(append(message, 'x'))
	if err := rsa.VerifyPSS(&privateKey.PublicKey, crypto.SHA256, digest[:], raw, &rsa.PSSOptions{SaltLength: 32, Hash: crypto.SHA256}); err == nil {
		t.Fatal("tampered message must not verify")
	}
}

func TestPrivateKeyParserAcceptsPKCS8(t *testing.T) {
	key, err := rsa.GenerateKey(rand.Reader, 3072)
	if err != nil { t.Fatal(err) }
	der, err := x509.MarshalPKCS8PrivateKey(key)
	if err != nil { t.Fatal(err) }
	pemBytes := pem.EncodeToMemory(&pem.Block{Type:"PRIVATE KEY", Bytes:der})
	parsed, err := parsePrivateKey(pemBytes)
	if err != nil { t.Fatal(err) }
	if parsed.N.BitLen() != 3072 { t.Fatal("unexpected RSA key size") }
}

func TestCanonicalPathRejectsQuery(t *testing.T) {
	if _, err := BuildCanonicalMessage("POST", "/api/license/verify?x=1", "1", "1234567890123456", nil); err == nil {
		t.Fatal("expected query path rejection")
	}
}
