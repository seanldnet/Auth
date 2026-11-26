package com.authplatform.licensesdk;

import org.junit.jupiter.api.Test;

import java.nio.charset.StandardCharsets;
import java.security.KeyPair;
import java.security.KeyPairGenerator;
import java.security.Signature;
import java.security.spec.MGF1ParameterSpec;
import java.security.spec.PSSParameterSpec;
import java.security.MessageDigest;

import static org.junit.jupiter.api.Assertions.*;

class LicenseClientTest {
    private static final PSSParameterSpec PSS = new PSSParameterSpec(
            "SHA-256", "MGF1", MGF1ParameterSpec.SHA256, 32, 1);

    @Test
    void canonicalMessageUsesExactBodyDigestAndUppercaseMethod() throws Exception {
        byte[] body = "{\"app_id\":\"app_123\"}".getBytes(StandardCharsets.UTF_8);
        String canonical = LicenseClient.buildCanonicalMessage(
                "post", "/api/license/verify", "1795700000",
                "0123456789abcdef0123456789abcdef", body);
        String hash = java.util.HexFormat.of().formatHex(MessageDigest.getInstance("SHA-256").digest(body));
        assertTrue(canonical.startsWith("POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n"));
        assertTrue(canonical.endsWith(hash));
    }

    @Test
    void rsaPssSignatureVerifiesAndTamperingFails() throws Exception {
        KeyPairGenerator generator = KeyPairGenerator.getInstance("RSA");
        generator.initialize(3072);
        KeyPair pair = generator.generateKeyPair();
        byte[] message = "signed request".getBytes(StandardCharsets.UTF_8);

        Signature signer = Signature.getInstance("RSASSA-PSS");
        signer.setParameter(PSS);
        signer.initSign(pair.getPrivate());
        signer.update(message);
        byte[] signature = signer.sign();

        Signature verifier = Signature.getInstance("RSASSA-PSS");
        verifier.setParameter(PSS);
        verifier.initVerify(pair.getPublic());
        verifier.update(message);
        assertTrue(verifier.verify(signature));

        verifier.initVerify(pair.getPublic());
        verifier.update("tampered request".getBytes(StandardCharsets.UTF_8));
        assertFalse(verifier.verify(signature));
    }

    @Test
    void canonicalPathRejectsQueryString() {
        assertThrows(IllegalArgumentException.class, () -> LicenseClient.buildCanonicalMessage(
                "POST", "/api/license/verify?x=1", "1", "0123456789abcdef", new byte[0]));
    }
}
