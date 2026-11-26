package com.authplatform.licensesdk.kotlin

import com.authplatform.licensesdk.LicenseClient
import org.junit.jupiter.api.Assertions.assertThrows
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test

class KotlinLicenseClientTest {
    @Test
    fun canonicalMessageMatchesSharedProtocol() {
        val body = """{"app_id":"app_123"}""".toByteArray()
        val canonical = LicenseClient.buildCanonicalMessage(
            "post", "/api/license/verify", "1795700000",
            "0123456789abcdef0123456789abcdef", body,
        )
        assertTrue(canonical.startsWith(
            "POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n",
        ))
    }

    @Test
    fun factoryRejectsNonHttpsEndpoints() {
        val generator = KeyPairGenerator.getInstance("RSA")
        generator.initialize(3072)
        val key = generator.generateKeyPair().private
        val pem = "-----BEGIN PRIVATE KEY-----\n" +
            Base64.getMimeEncoder(64, "\n".toByteArray()).encodeToString(key.encoded) +
            "\n-----END PRIVATE KEY-----"
        assertThrows(IllegalArgumentException::class.java) {
            KotlinLicenseClient.fromPem(
                "http://license.example.com",
                "app_0123456789abcdef01234567",
                "key_0123456789abcdef",
                pem,
            )
        }
    }
}
