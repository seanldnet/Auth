package authlicensesdk

import (
	"bytes"
	"context"
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"encoding/pem"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"regexp"
	"strconv"
	"strings"
	"time"
)

var (
	appIDPattern = regexp.MustCompile(`^app_[a-fA-F0-9]{24}$`)
	keyIDPattern = regexp.MustCompile(`^key_[a-f0-9]{16}$`)
)

type Client struct {
	baseURL string
	appID string
	keyID string
	privateKey *rsa.PrivateKey
	httpClient *http.Client
}

type LicenseResult struct {
	Valid bool `json:"valid"`
	Status string `json:"status"`
	LicenseNo string `json:"license_no"`
	ActivatedAt string `json:"activated_at"`
	ExpiresAt string `json:"expires_at"`
	DeviceLabel string `json:"device_label"`
	MachineChangeCount int `json:"machine_change_count"`
	Idempotent bool `json:"idempotent"`
	Message string `json:"message"`
}

type APIError struct {
	StatusCode int
	Message string
}

func (e *APIError) Error() string { return fmt.Sprintf("license API error (%d): %s", e.StatusCode, e.Message) }

func NewClient(baseURL, appID, keyID string, privateKeyPEM []byte, httpClient *http.Client) (*Client, error) {
	if !appIDPattern.MatchString(appID) { return nil, errors.New("valid software App ID is required") }
	if !keyIDPattern.MatchString(keyID) { return nil, errors.New("valid signing Key ID is required") }
	parsed, err := url.Parse(baseURL)
	if err != nil || parsed.Scheme == "" || parsed.Host == "" { return nil, errors.New("base URL must be an absolute URL") }
	key, err := parsePrivateKey(privateKeyPEM)
	if err != nil { return nil, err }
	if key.N.BitLen() < 3072 { return nil, errors.New("RSA-3072 or stronger private key is required") }
	if httpClient == nil { httpClient = &http.Client{Timeout: 10 * time.Second} }
	return &Client{baseURL: strings.TrimRight(baseURL, "/"), appID: appID, keyID: keyID, privateKey: key, httpClient: httpClient}, nil
}

func parsePrivateKey(data []byte) (*rsa.PrivateKey, error) {
	block, _ := pem.Decode(data)
	if block == nil { return nil, errors.New("invalid PEM private key") }
	if key, err := x509.ParsePKCS8PrivateKey(block.Bytes); err == nil {
		rsaKey, ok := key.(*rsa.PrivateKey)
		if !ok { return nil, errors.New("PEM key must be RSA") }
		return rsaKey, nil
	}
	if key, err := x509.ParsePKCS1PrivateKey(block.Bytes); err == nil { return key, nil }
	return nil, errors.New("unable to parse RSA private key")
}

func BuildCanonicalMessage(method, path, timestamp, nonce string, body []byte) ([]byte, error) {
	if !strings.HasPrefix(path, "/") || strings.Contains(path, "?") {
		return nil, errors.New("signed path must start with / and must not contain a query string")
	}
	digest := sha256.Sum256(body)
	message := strings.Join([]string{strings.ToUpper(method), path, timestamp, nonce, fmt.Sprintf("%x", digest[:])}, "\n")
	return []byte(message), nil
}

func Sign(privateKey *rsa.PrivateKey, canonical []byte) (string, error) {
	digest := sha256.Sum256(canonical)
	signature, err := rsa.SignPSS(rand.Reader, privateKey, crypto.SHA256, digest[:], &rsa.PSSOptions{SaltLength: 32, Hash: crypto.SHA256})
	if err != nil { return "", err }
	return base64.StdEncoding.EncodeToString(signature), nil
}

func (c *Client) Activate(ctx context.Context, licenseKey, fingerprint, deviceLabel string) (*LicenseResult, error) {
	return c.post(ctx, "/api/license/activate", map[string]any{
		"app_id": c.appID, "license_key": licenseKey, "device_fingerprint": fingerprint, "device_label": deviceLabel,
	})
}

func (c *Client) Verify(ctx context.Context, licenseKey, fingerprint string) (*LicenseResult, error) {
	return c.post(ctx, "/api/license/verify", map[string]any{
		"app_id": c.appID, "license_key": licenseKey, "device_fingerprint": fingerprint,
	})
}

func (c *Client) ChangeDevice(ctx context.Context, licenseKey, currentFingerprint, newFingerprint, deviceLabel string) (*LicenseResult, error) {
	return c.post(ctx, "/api/license/change-device", map[string]any{
		"app_id": c.appID, "license_key": licenseKey, "current_device_fingerprint": currentFingerprint,
		"new_device_fingerprint": newFingerprint, "device_label": deviceLabel,
	})
}

func (c *Client) Unbind(ctx context.Context, licenseKey, fingerprint string) (*LicenseResult, error) {
	return c.post(ctx, "/api/license/unbind", map[string]any{
		"app_id": c.appID, "license_key": licenseKey, "device_fingerprint": fingerprint,
	})
}

func (c *Client) post(ctx context.Context, path string, payload any) (*LicenseResult, error) {
	body, err := json.Marshal(payload)
	if err != nil { return nil, err }
	timestamp := strconv.FormatInt(time.Now().Unix(), 10)
	nonceBytes := make([]byte, 32)
	if _, err := rand.Read(nonceBytes); err != nil { return nil, err }
	nonce := fmt.Sprintf("%x", nonceBytes)
	canonical, err := BuildCanonicalMessage(http.MethodPost, path, timestamp, nonce, body)
	if err != nil { return nil, err }
	signature, err := Sign(c.privateKey, canonical)
	if err != nil { return nil, err }

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, c.baseURL+path, bytes.NewReader(body))
	if err != nil { return nil, err }
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-App-Id", c.appID)
	req.Header.Set("X-Key-Id", c.keyID)
	req.Header.Set("X-Timestamp", timestamp)
	req.Header.Set("X-Nonce", nonce)
	req.Header.Set("X-Signature", signature)

	response, err := c.httpClient.Do(req)
	if err != nil { return nil, err }
	defer response.Body.Close()
	responseBody, err := io.ReadAll(io.LimitReader(response.Body, 1<<20))
	if err != nil { return nil, err }
	var envelope struct {
		Success bool `json:"success"`
		Data *LicenseResult `json:"data"`
		Message string `json:"message"`
	}
	if err := json.Unmarshal(responseBody, &envelope); err != nil {
		return nil, &APIError{StatusCode: response.StatusCode, Message: "invalid JSON response"}
	}
	if response.StatusCode < 200 || response.StatusCode >= 300 || !envelope.Success || envelope.Data == nil {
		return nil, &APIError{StatusCode: response.StatusCode, Message: envelope.Message}
	}
	return envelope.Data, nil
}
