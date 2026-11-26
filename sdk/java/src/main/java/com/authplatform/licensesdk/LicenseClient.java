package com.authplatform.licensesdk;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.databind.PropertyNamingStrategies;

import java.io.IOException;
import java.math.BigInteger;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.security.*;
import java.security.interfaces.RSAPrivateKey;
import java.security.spec.MGF1ParameterSpec;
import java.security.spec.PKCS8EncodedKeySpec;
import java.security.spec.PSSParameterSpec;
import java.time.Duration;
import java.util.Base64;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.regex.Pattern;

public final class LicenseClient {
    private static final Pattern APP_ID = Pattern.compile("^app_[a-fA-F0-9]{24}$");
    private static final Pattern KEY_ID = Pattern.compile("^key_[a-f0-9]{16}$");
    private static final PSSParameterSpec PSS = new PSSParameterSpec(
            "SHA-256", "MGF1", MGF1ParameterSpec.SHA256, 32, 1);
    private final HttpClient http;
    private final URI baseUri;
    private final String appId;
    private final String keyId;
    private final PrivateKey privateKey;
    private final ObjectMapper mapper;

    public LicenseClient(URI baseUri, String appId, String keyId, String privateKeyPem) throws GeneralSecurityException {
        this(HttpClient.newBuilder().connectTimeout(Duration.ofSeconds(10)).build(),
                baseUri, appId, keyId, loadPrivateKey(privateKeyPem));
    }

    public LicenseClient(HttpClient http, URI baseUri, String appId, String keyId, PrivateKey privateKey) {
        if (http == null || baseUri == null || baseUri.getHost() == null) throw new IllegalArgumentException("An absolute API base URI is required.");
        if (!APP_ID.matcher(appId).matches()) throw new IllegalArgumentException("A valid software App ID is required.");
        if (!KEY_ID.matcher(keyId).matches()) throw new IllegalArgumentException("A valid signing Key ID is required.");
        if (!(privateKey instanceof RSAPrivateKey rsaKey) || rsaKey.getModulus().bitLength() < 3072) {
            throw new IllegalArgumentException("RSA-3072 or stronger private key is required.");
        }
        this.http = http;
        this.baseUri = baseUri;
        this.appId = appId;
        this.keyId = keyId;
        this.privateKey = privateKey;
        this.mapper = new ObjectMapper().setPropertyNamingStrategy(PropertyNamingStrategies.SNAKE_CASE);
    }

    public static PrivateKey loadPrivateKey(String pem) throws GeneralSecurityException {
        if (pem == null || pem.isBlank()) throw new IllegalArgumentException("RSA private key PEM is required.");
        String encoded = pem.replace("-----BEGIN PRIVATE KEY-----", "")
                .replace("-----END PRIVATE KEY-----", "")
                .replaceAll("\\s", "");
        byte[] der;
        try { der = Base64.getDecoder().decode(encoded); }
        catch (IllegalArgumentException exception) { throw new InvalidKeyException("Invalid PKCS#8 PEM encoding.", exception); }
        PrivateKey key = KeyFactory.getInstance("RSA").generatePrivate(new PKCS8EncodedKeySpec(der));
        if (!(key instanceof RSAPrivateKey rsaKey) || rsaKey.getModulus().bitLength() < 3072) {
            throw new InvalidKeyException("RSA-3072 or stronger private key is required.");
        }
        return key;
    }

    public LicenseResult activate(String licenseKey, String fingerprint, String deviceLabel) throws IOException, InterruptedException, GeneralSecurityException {
        Map<String, Object> body = payload("license_key", licenseKey, "device_fingerprint", fingerprint);
        if (deviceLabel != null) body.put("device_label", deviceLabel);
        return post("/api/license/activate", body);
    }

    public LicenseResult verify(String licenseKey, String fingerprint) throws IOException, InterruptedException, GeneralSecurityException {
        return post("/api/license/verify", payload("license_key", licenseKey, "device_fingerprint", fingerprint));
    }

    public LicenseResult changeDevice(String licenseKey, String currentFingerprint, String newFingerprint, String deviceLabel)
            throws IOException, InterruptedException, GeneralSecurityException {
        Map<String, Object> body = payload("license_key", licenseKey,
                "current_device_fingerprint", currentFingerprint, "new_device_fingerprint", newFingerprint);
        if (deviceLabel != null) body.put("device_label", deviceLabel);
        return post("/api/license/change-device", body);
    }

    public LicenseResult unbind(String licenseKey, String fingerprint) throws IOException, InterruptedException, GeneralSecurityException {
        return post("/api/license/unbind", payload("license_key", licenseKey, "device_fingerprint", fingerprint));
    }

    private Map<String, Object> payload(String... pairs) {
        Map<String, Object> values = new LinkedHashMap<>();
        values.put("app_id", appId);
        for (int i = 0; i < pairs.length; i += 2) values.put(pairs[i], pairs[i + 1]);
        return values;
    }

    private LicenseResult post(String path, Map<String, Object> payload)
            throws IOException, InterruptedException, GeneralSecurityException {
        byte[] body = mapper.writeValueAsBytes(payload);
        String timestamp = Long.toString(System.currentTimeMillis() / 1000);
        byte[] nonceBytes = new byte[32];
        SecureRandom.getInstanceStrong().nextBytes(nonceBytes);
        String nonce = java.util.HexFormat.of().formatHex(nonceBytes);
        String canonical = buildCanonicalMessage("POST", path, timestamp, nonce, body);
        Signature signer = Signature.getInstance("RSASSA-PSS");
        signer.setParameter(PSS);
        signer.initSign(privateKey);
        signer.update(canonical.getBytes(StandardCharsets.UTF_8));
        String signature = Base64.getEncoder().encodeToString(signer.sign());

        HttpRequest request = HttpRequest.newBuilder(baseUri.resolve(path))
                .timeout(Duration.ofSeconds(10))
                .header("Accept", "application/json")
                .header("Content-Type", "application/json")
                .header("X-App-Id", appId)
                .header("X-Key-Id", keyId)
                .header("X-Timestamp", timestamp)
                .header("X-Nonce", nonce)
                .header("X-Signature", signature)
                .POST(HttpRequest.BodyPublishers.ofByteArray(body))
                .build();
        HttpResponse<String> response = http.send(request, HttpResponse.BodyHandlers.ofString(StandardCharsets.UTF_8));
        JsonNode envelope = mapper.readTree(response.body());
        if (response.statusCode() < 200 || response.statusCode() >= 300
                || !envelope.path("success").asBoolean(false) || envelope.path("data").isMissingNode()) {
            throw new LicenseApiException(response.statusCode(), envelope.path("message").asText("License API request failed."));
        }
        return mapper.treeToValue(envelope.path("data"), LicenseResult.class);
    }

    public static String buildCanonicalMessage(String method, String path, String timestamp, String nonce, byte[] body)
            throws GeneralSecurityException {
        if (path == null || !path.startsWith("/") || path.contains("?")) {
            throw new IllegalArgumentException("Signed path must start with / and must not contain a query string.");
        }
        byte[] digest = MessageDigest.getInstance("SHA-256").digest(body);
        return String.join("\n", method.toUpperCase(), path, timestamp, nonce, java.util.HexFormat.of().formatHex(digest));
    }
}
