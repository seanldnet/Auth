#ifndef AUTH_LICENSE_EASY_BRIDGE_H
#define AUTH_LICENSE_EASY_BRIDGE_H

#ifdef _WIN32
#define AUTH_EASY_EXPORT __declspec(dllexport)
#define AUTH_EASY_CALL __stdcall
#else
#define AUTH_EASY_EXPORT __attribute__((visibility("default")))
#define AUTH_EASY_CALL
#endif

#ifdef __cplusplus
extern "C" {
#endif

typedef void *AUTH_LICENSE_HANDLE;

/*
 * UTF-8 C ABI for 易语言 DLL commands.
 * Every returned result/error string must be released by AuthLicense_FreeString.
 */
AUTH_EASY_EXPORT AUTH_LICENSE_HANDLE AUTH_EASY_CALL AuthLicense_Create(
    const char *https_base_url, const char *app_id, const char *key_id,
    const char *private_key_pem, int timeout_seconds, char **error_message);
AUTH_EASY_EXPORT void AUTH_EASY_CALL AuthLicense_Destroy(AUTH_LICENSE_HANDLE handle);

AUTH_EASY_EXPORT char *AUTH_EASY_CALL AuthLicense_Activate(
    AUTH_LICENSE_HANDLE handle, const char *license_key, const char *device_fingerprint,
    const char *device_label, long *http_status, char **error_message);
AUTH_EASY_EXPORT char *AUTH_EASY_CALL AuthLicense_Verify(
    AUTH_LICENSE_HANDLE handle, const char *license_key, const char *device_fingerprint,
    long *http_status, char **error_message);
AUTH_EASY_EXPORT char *AUTH_EASY_CALL AuthLicense_ChangeDevice(
    AUTH_LICENSE_HANDLE handle, const char *license_key, const char *current_fingerprint,
    const char *new_fingerprint, const char *device_label,
    long *http_status, char **error_message);
AUTH_EASY_EXPORT char *AUTH_EASY_CALL AuthLicense_Unbind(
    AUTH_LICENSE_HANDLE handle, const char *license_key, const char *device_fingerprint,
    long *http_status, char **error_message);
AUTH_EASY_EXPORT void AUTH_EASY_CALL AuthLicense_FreeString(char *value);

#ifdef __cplusplus
}
#endif
#endif
