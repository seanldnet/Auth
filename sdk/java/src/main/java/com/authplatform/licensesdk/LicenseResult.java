package com.authplatform.licensesdk;

public record LicenseResult(
        boolean valid,
        String status,
        String licenseNo,
        String activatedAt,
        String expiresAt,
        String deviceLabel,
        int machineChangeCount,
        boolean idempotent,
        String message) { }
