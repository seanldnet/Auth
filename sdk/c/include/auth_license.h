#ifndef AUTH_LICENSE_SDK_H
#define AUTH_LICENSE_SDK_H

#ifdef __cplusplus
extern "C" {
#endif

typedef struct auth_license_client auth_license_client;

/* All returned strings are heap allocated and must be released with auth_license_free_string. */
auth_license_client *auth_license_client_new(
    const char *base_url, const char *app_id, const char *key_id,
    const char *private_key_pem, long timeout_seconds, char **error_message);
void auth_license_client_free(auth_license_client *client);

char *auth_license_activate(auth_license_client *client, const char *license_key,
    const char *device_fingerprint, const char *device_label,
    long *http_status, char **error_message);
char *auth_license_verify(auth_license_client *client, const char *license_key,
    const char *device_fingerprint, long *http_status, char **error_message);
char *auth_license_change_device(auth_license_client *client, const char *license_key,
    const char *current_fingerprint, const char *new_fingerprint,
    const char *device_label, long *http_status, char **error_message);
char *auth_license_unbind(auth_license_client *client, const char *license_key,
    const char *device_fingerprint, long *http_status, char **error_message);

void auth_license_free_string(char *value);

#ifdef __cplusplus
}
#endif
#endif
