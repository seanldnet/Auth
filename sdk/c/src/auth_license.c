#include "auth_license.h"
#include "auth_license_internal.h"

#include <curl/curl.h>
#include <json-c/json.h>
#include <openssl/bio.h>
#include <openssl/err.h>
#include <openssl/pem.h>
#include <openssl/rand.h>
#include <openssl/rsa.h>
#include <openssl/sha.h>
#include <openssl/provider.h>

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <pthread.h>

#define MAX_RESPONSE_BYTES (1024U * 1024U)

struct auth_license_client {
    char *base_url;
    char *app_id;
    char *key_id;
    EVP_PKEY *private_key;
    long timeout_seconds;
};

struct response_buffer {
    char *data;
    size_t length;
    size_t capacity;
};

static pthread_once_t crypto_once = PTHREAD_ONCE_INIT;
static pthread_once_t curl_once = PTHREAD_ONCE_INIT;
static int crypto_ready = 0;
static int curl_ready = 0;

static void initialize_crypto(void) {
    crypto_ready = OSSL_PROVIDER_load(NULL, "default") != NULL;
}

static void initialize_curl(void) {
    curl_ready = curl_global_init(CURL_GLOBAL_DEFAULT) == CURLE_OK;
}

static char *duplicate_string(const char *value) {
    if (value == NULL) return NULL;
    size_t length = strlen(value);
    char *copy = (char *)malloc(length + 1);
    if (copy != NULL) memcpy(copy, value, length + 1);
    return copy;
}

static int is_hex_string(const char *value) {
    if (value == NULL) return 0;
    for (const char *p = value; *p; ++p) {
        if (!((*p >= '0' && *p <= '9') || (*p >= 'a' && *p <= 'f') || (*p >= 'A' && *p <= 'F'))) return 0;
    }
    return 1;
}

static void set_error(char **target, const char *message) {
    if (target != NULL) *target = duplicate_string(message != NULL ? message : "License SDK error");
}

void auth_license_free_string(char *value) { free(value); }

static char *hex_encode(const unsigned char *bytes, size_t length) {
    static const char digits[] = "0123456789abcdef";
    char *result = (char *)malloc(length * 2 + 1);
    if (result == NULL) return NULL;
    for (size_t i = 0; i < length; ++i) {
        result[i * 2] = digits[bytes[i] >> 4];
        result[i * 2 + 1] = digits[bytes[i] & 0x0f];
    }
    result[length * 2] = '\0';
    return result;
}

char *auth_license_build_canonical_message(const char *method, const char *path,
    const char *timestamp, const char *nonce, const unsigned char *body, size_t body_length) {
    if (method == NULL || path == NULL || path[0] != '/' || strchr(path, '?') != NULL
        || timestamp == NULL || nonce == NULL) return NULL;
    unsigned char digest[SHA256_DIGEST_LENGTH];
    SHA256(body, body_length, digest);
    char *body_hash = hex_encode(digest, sizeof(digest));
    if (body_hash == NULL) return NULL;
    size_t capacity = strlen(method) + strlen(path) + strlen(timestamp) + strlen(nonce) + strlen(body_hash) + 8;
    char *message = (char *)malloc(capacity);
    if (message == NULL) { free(body_hash); return NULL; }
    size_t offset = 0;
    for (const char *p = method; *p; ++p) message[offset++] = (char)(*p >= 'a' && *p <= 'z' ? *p - 32 : *p);
    message[offset++] = '\n';
    offset += (size_t)sprintf(message + offset, "%s\n%s\n%s\n%s", path, timestamp, nonce, body_hash);
    free(body_hash);
    return message;
}

char *auth_license_sign_rsa_pss_sha256(EVP_PKEY *private_key, const char *message) {
    if (private_key == NULL || message == NULL) return NULL;
    pthread_once(&crypto_once, initialize_crypto);
    if (!crypto_ready) return NULL;
    unsigned char digest[SHA256_DIGEST_LENGTH];
    SHA256((const unsigned char *)message, strlen(message), digest);
    EVP_PKEY_CTX *context = EVP_PKEY_CTX_new(private_key, NULL);
    if (context == NULL) return NULL;
    size_t signature_length = 0;
    unsigned char *signature = NULL;
    int ok = EVP_PKEY_sign_init(context) > 0
        && EVP_PKEY_CTX_set_rsa_padding(context, RSA_PKCS1_PSS_PADDING) > 0
        && EVP_PKEY_CTX_set_signature_md(context, EVP_sha256()) > 0
        && EVP_PKEY_CTX_set_rsa_mgf1_md(context, EVP_sha256()) > 0
        && EVP_PKEY_CTX_set_rsa_pss_saltlen(context, 32) > 0
        && EVP_PKEY_sign(context, NULL, &signature_length, digest, sizeof(digest)) > 0;
    if (ok) {
        signature = (unsigned char *)malloc(signature_length);
        ok = signature != NULL && EVP_PKEY_sign(context, signature, &signature_length, digest, sizeof(digest)) > 0;
    }
    EVP_PKEY_CTX_free(context);
    if (!ok) { free(signature); return NULL; }
    size_t encoded_length = 4 * ((signature_length + 2) / 3);
    char *encoded = (char *)malloc(encoded_length + 1);
    if (encoded == NULL) { free(signature); return NULL; }
    int written = EVP_EncodeBlock((unsigned char *)encoded, signature, (int)signature_length);
    free(signature);
    if (written < 0) { free(encoded); return NULL; }
    encoded[written] = '\0';
    return encoded;
}

static size_t write_response(char *data, size_t size, size_t count, void *context) {
    size_t bytes = size * count;
    struct response_buffer *buffer = (struct response_buffer *)context;
    if (buffer->length + bytes > MAX_RESPONSE_BYTES) return 0;
    if (buffer->length + bytes + 1 > buffer->capacity) {
        size_t capacity = buffer->capacity == 0 ? 4096 : buffer->capacity;
        while (capacity < buffer->length + bytes + 1) capacity *= 2;
        char *next = (char *)realloc(buffer->data, capacity);
        if (next == NULL) return 0;
        buffer->data = next;
        buffer->capacity = capacity;
    }
    memcpy(buffer->data + buffer->length, data, bytes);
    buffer->length += bytes;
    buffer->data[buffer->length] = '\0';
    return bytes;
}

auth_license_client *auth_license_client_new(const char *base_url, const char *app_id,
    const char *key_id, const char *private_key_pem, long timeout_seconds, char **error_message) {
    if (error_message != NULL) *error_message = NULL;
    if (base_url == NULL || strncmp(base_url, "https://", 8) != 0
        || strchr(base_url + 8, '/') != NULL || strchr(base_url + 8, '?') != NULL
        || strchr(base_url + 8, '#') != NULL) {
        set_error(error_message, "The API base URL must be an absolute HTTPS origin.");
        return NULL;
    }
    if (app_id == NULL || strlen(app_id) != 28 || strncmp(app_id, "app_", 4) != 0 || !is_hex_string(app_id + 4)
        || key_id == NULL || strlen(key_id) != 20 || strncmp(key_id, "key_", 4) != 0 || !is_hex_string(key_id + 4)) {
        set_error(error_message, "A valid software App ID and signing Key ID are required.");
        return NULL;
    }
    if (private_key_pem == NULL || timeout_seconds < 1 || timeout_seconds > 120) {
        set_error(error_message, "A private key and timeout between 1 and 120 seconds are required.");
        return NULL;
    }
    pthread_once(&crypto_once, initialize_crypto);
    pthread_once(&curl_once, initialize_curl);
    if (!crypto_ready || !curl_ready) {
        set_error(error_message, "Unable to initialize OpenSSL or libcurl.");
        return NULL;
    }

    BIO *bio = BIO_new_mem_buf(private_key_pem, -1);
    EVP_PKEY *key = bio != NULL ? PEM_read_bio_PrivateKey(bio, NULL, NULL, NULL) : NULL;
    BIO_free(bio);
    if (key == NULL || EVP_PKEY_base_id(key) != EVP_PKEY_RSA || EVP_PKEY_get_bits(key) < 3072) {
        EVP_PKEY_free(key);
        set_error(error_message, "RSA-3072 or stronger private key is required.");
        return NULL;
    }

    auth_license_client *client = (auth_license_client *)calloc(1, sizeof(*client));
    if (client == NULL) { EVP_PKEY_free(key); set_error(error_message, "Out of memory."); return NULL; }
    client->base_url = duplicate_string(base_url);
    client->app_id = duplicate_string(app_id);
    client->key_id = duplicate_string(key_id);
    client->private_key = key;
    client->timeout_seconds = timeout_seconds;
    if (client->base_url == NULL || client->app_id == NULL || client->key_id == NULL) {
        auth_license_client_free(client);
        set_error(error_message, "Out of memory.");
        return NULL;
    }
    return client;
}

void auth_license_client_free(auth_license_client *client) {
    if (client == NULL) return;
    free(client->base_url); free(client->app_id); free(client->key_id);
    EVP_PKEY_free(client->private_key);
    free(client);
}

static void add_json_string(struct json_object *object, const char *key, const char *value) {
    if (value != NULL) json_object_object_add(object, key, json_object_new_string(value));
}

static char *post_json(auth_license_client *client, const char *path, struct json_object *payload,
    long *http_status, char **error_message) {
    if (http_status != NULL) *http_status = 0;
    if (error_message != NULL) *error_message = NULL;
    if (client == NULL || payload == NULL) { set_error(error_message, "Invalid client or request payload."); return NULL; }

    const char *body = json_object_to_json_string_ext(payload, JSON_C_TO_STRING_PLAIN | JSON_C_TO_STRING_NOSLASHESCAPE);
    if (body == NULL) { set_error(error_message, "Unable to serialize request JSON."); return NULL; }
    size_t body_length = strlen(body);
    char timestamp[32];
    snprintf(timestamp, sizeof(timestamp), "%lld", (long long)time(NULL));
    unsigned char nonce_bytes[32];
    if (RAND_bytes(nonce_bytes, sizeof(nonce_bytes)) != 1) { set_error(error_message, "Unable to generate request nonce."); return NULL; }
    char *nonce = hex_encode(nonce_bytes, sizeof(nonce_bytes));
    char *canonical = nonce != NULL ? auth_license_build_canonical_message("POST", path, timestamp, nonce,
        (const unsigned char *)body, body_length) : NULL;
    char *signature = canonical != NULL ? auth_license_sign_rsa_pss_sha256(client->private_key, canonical) : NULL;
    free(canonical);
    if (nonce == NULL || signature == NULL) {
        free(nonce); free(signature);
        set_error(error_message, "Unable to create RSA-PSS request signature.");
        return NULL;
    }

    struct curl_slist *headers = NULL;
    char header[1200];
    headers = curl_slist_append(headers, "Accept: application/json");
    headers = curl_slist_append(headers, "Content-Type: application/json");
    snprintf(header, sizeof(header), "X-App-Id: %s", client->app_id); headers = curl_slist_append(headers, header);
    snprintf(header, sizeof(header), "X-Key-Id: %s", client->key_id); headers = curl_slist_append(headers, header);
    snprintf(header, sizeof(header), "X-Timestamp: %s", timestamp); headers = curl_slist_append(headers, header);
    snprintf(header, sizeof(header), "X-Nonce: %s", nonce); headers = curl_slist_append(headers, header);
    snprintf(header, sizeof(header), "X-Signature: %s", signature); headers = curl_slist_append(headers, header);
    free(nonce); free(signature);

    struct response_buffer response = {0};
    CURL *curl = curl_easy_init();
    if (curl == NULL || headers == NULL) {
        curl_easy_cleanup(curl); curl_slist_free_all(headers);
        set_error(error_message, "Unable to initialize HTTP request.");
        return NULL;
    }
    char *url = (char *)malloc(strlen(client->base_url) + strlen(path) + 1);
    if (url == NULL) { curl_easy_cleanup(curl); curl_slist_free_all(headers); set_error(error_message, "Out of memory."); return NULL; }
    sprintf(url, "%s%s", client->base_url, path);
    curl_easy_setopt(curl, CURLOPT_URL, url);
    curl_easy_setopt(curl, CURLOPT_POST, 1L);
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS, body);
    curl_easy_setopt(curl, CURLOPT_POSTFIELDSIZE_LARGE, (curl_off_t)body_length);
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, write_response);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &response);
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, client->timeout_seconds < 5 ? client->timeout_seconds : 5);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, client->timeout_seconds);
    curl_easy_setopt(curl, CURLOPT_SSL_VERIFYPEER, 1L);
    curl_easy_setopt(curl, CURLOPT_SSL_VERIFYHOST, 2L);
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 0L);
    CURLcode curl_result = curl_easy_perform(curl);
    long status = 0; curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &status);
    curl_easy_cleanup(curl); curl_slist_free_all(headers); free(url);
    if (http_status != NULL) *http_status = status;
    if (curl_result != CURLE_OK) {
        free(response.data);
        set_error(error_message, curl_easy_strerror(curl_result));
        return NULL;
    }

    struct json_object *envelope = response.data != NULL ? json_tokener_parse(response.data) : NULL;
    free(response.data);
    struct json_object *success = NULL, *data = NULL, *message = NULL;
    if (envelope == NULL || !json_object_object_get_ex(envelope, "success", &success)
        || !json_object_get_boolean(success) || !json_object_object_get_ex(envelope, "data", &data)
        || !json_object_is_type(data, json_type_object) || status < 200 || status >= 300) {
        const char *message_text = envelope != NULL && json_object_object_get_ex(envelope, "message", &message)
            ? json_object_get_string(message) : "License API request failed.";
        set_error(error_message, message_text);
        if (envelope != NULL) json_object_put(envelope);
        return NULL;
    }
    const char *result = json_object_to_json_string_ext(data, JSON_C_TO_STRING_PLAIN);
    char *copy = duplicate_string(result);
    json_object_put(envelope);
    if (copy == NULL) set_error(error_message, "Out of memory.");
    return copy;
}

static struct json_object *new_payload(auth_license_client *client, const char *license_key,
    const char *fingerprint) {
    struct json_object *payload = json_object_new_object();
    if (payload == NULL) return NULL;
    add_json_string(payload, "app_id", client->app_id);
    add_json_string(payload, "license_key", license_key);
    add_json_string(payload, "device_fingerprint", fingerprint);
    return payload;
}

char *auth_license_activate(auth_license_client *client, const char *license_key,
    const char *fingerprint, const char *label, long *status, char **error) {
    if (client == NULL) { set_error(error, "Invalid client."); return NULL; }
    struct json_object *payload = new_payload(client, license_key, fingerprint);
    if (payload != NULL) add_json_string(payload, "device_label", label);
    char *result = post_json(client, "/api/license/activate", payload, status, error);
    if (payload != NULL) json_object_put(payload);
    return result;
}

char *auth_license_verify(auth_license_client *client, const char *license_key,
    const char *fingerprint, long *status, char **error) {
    if (client == NULL) { set_error(error, "Invalid client."); return NULL; }
    struct json_object *payload = new_payload(client, license_key, fingerprint);
    char *result = post_json(client, "/api/license/verify", payload, status, error);
    if (payload != NULL) json_object_put(payload);
    return result;
}

char *auth_license_change_device(auth_license_client *client, const char *license_key,
    const char *current_fingerprint, const char *new_fingerprint, const char *label,
    long *status, char **error) {
    if (client == NULL) { set_error(error, "Invalid client."); return NULL; }
    struct json_object *payload = json_object_new_object();
    if (payload != NULL) {
        add_json_string(payload, "app_id", client->app_id);
        add_json_string(payload, "license_key", license_key);
        add_json_string(payload, "current_device_fingerprint", current_fingerprint);
        add_json_string(payload, "new_device_fingerprint", new_fingerprint);
        add_json_string(payload, "device_label", label);
    }
    char *result = post_json(client, "/api/license/change-device", payload, status, error);
    if (payload != NULL) json_object_put(payload);
    return result;
}

char *auth_license_unbind(auth_license_client *client, const char *license_key,
    const char *fingerprint, long *status, char **error) {
    if (client == NULL) { set_error(error, "Invalid client."); return NULL; }
    struct json_object *payload = new_payload(client, license_key, fingerprint);
    char *result = post_json(client, "/api/license/unbind", payload, status, error);
    if (payload != NULL) json_object_put(payload);
    return result;
}
