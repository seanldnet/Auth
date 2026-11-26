package com.authplatform.licensesdk;

public final class LicenseApiException extends RuntimeException {
    private final int statusCode;

    public LicenseApiException(int statusCode, String message) {
        super(message);
        this.statusCode = statusCode;
    }

    public int getStatusCode() { return statusCode; }
}
