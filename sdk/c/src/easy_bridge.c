#include "auth_license_easy_bridge.h"
#include "auth_license.h"

AUTH_LICENSE_HANDLE AUTH_EASY_CALL AuthLicense_Create(
    const char *base_url, const char *app_id, const char *key_id,
    const char *private_key_pem, int timeout_seconds, char **error_message) {
    return (AUTH_LICENSE_HANDLE)auth_license_client_new(
        base_url, app_id, key_id, private_key_pem, (long)timeout_seconds, error_message);
}

void AUTH_EASY_CALL AuthLicense_Destroy(AUTH_LICENSE_HANDLE handle) {
    auth_license_client_free((auth_license_client *)handle);
}

char *AUTH_EASY_CALL AuthLicense_Activate(
    AUTH_LICENSE_HANDLE handle, const char *license_key, const char *fingerprint,
    const char *label, long *http_status, char **error_message) {
    return auth_license_activate((auth_license_client *)handle, license_key, fingerprint,
        label, http_status, error_message);
}

char *AUTH_EASY_CALL AuthLicense_Verify(
    AUTH_LICENSE_HANDLE handle, const char *license_key, const char *fingerprint,
    long *http_status, char **error_message) {
    return auth_license_verify((auth_license_client *)handle, license_key, fingerprint,
        http_status, error_message);
}

char *AUTH_EASY_CALL AuthLicense_ChangeDevice(
    AUTH_LICENSE_HANDLE handle, const char *license_key, const char *current_fingerprint,
    const char *new_fingerprint, const char *label, long *http_status, char **error_message) {
    return auth_license_change_device((auth_license_client *)handle, license_key,
        current_fingerprint, new_fingerprint, label, http_status, error_message);
}

char *AUTH_EASY_CALL AuthLicense_Unbind(
    AUTH_LICENSE_HANDLE handle, const char *license_key, const char *fingerprint,
    long *http_status, char **error_message) {
    return auth_license_unbind((auth_license_client *)handle, license_key, fingerprint,
        http_status, error_message);
}

void AUTH_EASY_CALL AuthLicense_FreeString(char *value) {
    auth_license_free_string(value);
}
