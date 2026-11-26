package com.authplatform.licensesdk.kotlin

import com.authplatform.licensesdk.LicenseClient as JavaLicenseClient
import com.authplatform.licensesdk.LicenseResult
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.net.URI

/** Coroutine-friendly Kotlin facade over the protocol-tested Java 17 SDK. */
class KotlinLicenseClient private constructor(
    private val delegate: JavaLicenseClient,
) {
    suspend fun activate(licenseKey: String, deviceFingerprint: String, deviceLabel: String? = null): LicenseResult =
        withContext(Dispatchers.IO) { delegate.activate(licenseKey, deviceFingerprint, deviceLabel) }

    suspend fun verify(licenseKey: String, deviceFingerprint: String): LicenseResult =
        withContext(Dispatchers.IO) { delegate.verify(licenseKey, deviceFingerprint) }

    suspend fun changeDevice(
        licenseKey: String,
        currentFingerprint: String,
        newFingerprint: String,
        deviceLabel: String? = null,
    ): LicenseResult = withContext(Dispatchers.IO) {
        delegate.changeDevice(licenseKey, currentFingerprint, newFingerprint, deviceLabel)
    }

    suspend fun unbind(licenseKey: String, deviceFingerprint: String): LicenseResult =
        withContext(Dispatchers.IO) { delegate.unbind(licenseKey, deviceFingerprint) }

    companion object {
        @JvmStatic
        fun fromPem(baseUrl: String, appId: String, keyId: String, privateKeyPem: String): KotlinLicenseClient =
            KotlinLicenseClient(JavaLicenseClient(URI.create(baseUrl), appId, keyId, privateKeyPem))
    }
}
