#include "auth_license_internal.h"
#include <openssl/rsa.h>
#include <openssl/sha.h>
#include <openssl/provider.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define CHECK(condition) do { if (!(condition)) { fprintf(stderr, "check failed: %s\n", #condition); return 1; } } while (0)

int main(void) {
    OSSL_PROVIDER_load(NULL, "default");
    const char body[] = "{\"app_id\":\"app_123\"}";
    char *canonical = auth_license_build_canonical_message("post", "/api/license/verify",
        "1795700000", "0123456789abcdef0123456789abcdef", (const unsigned char *)body, strlen(body));
    CHECK(canonical != NULL);
    const char *prefix = "POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n";
    CHECK(strncmp(canonical, prefix, strlen(prefix)) == 0);
    free(canonical);
    CHECK(auth_license_build_canonical_message("POST", "/api/license/verify?x=1",
        "1", "0123456789abcdef", (const unsigned char *)"", 0) == NULL);

    EVP_PKEY_CTX *key_context = EVP_PKEY_CTX_new_id(EVP_PKEY_RSA, NULL);
    CHECK(key_context != NULL);
    CHECK(EVP_PKEY_keygen_init(key_context) > 0);
    CHECK(EVP_PKEY_CTX_set_rsa_keygen_bits(key_context, 3072) > 0);
    EVP_PKEY *key = NULL;
    CHECK(EVP_PKEY_keygen(key_context, &key) > 0);
    EVP_PKEY_CTX_free(key_context);

    char *signature = auth_license_sign_rsa_pss_sha256(key, "signed request");
    CHECK(signature != NULL);
    size_t sig_len = strlen(signature);
    unsigned char *decoded = (unsigned char *)calloc(sig_len, 1);
    CHECK(decoded != NULL);
    int decoded_len = EVP_DecodeBlock(decoded, (const unsigned char *)signature, (int)sig_len);
    CHECK(decoded_len > 0);
    size_t padding = sig_len >= 2 && strcmp(signature + sig_len - 2, "==") == 0 ? 2
        : (sig_len > 0 && signature[sig_len - 1] == '=' ? 1 : 0);
    decoded_len -= (int)padding;

    unsigned char digest[SHA256_DIGEST_LENGTH];
    SHA256((const unsigned char *)"signed request", strlen("signed request"), digest);
    EVP_PKEY_CTX *verify_context = EVP_PKEY_CTX_new(key, NULL);
    CHECK(verify_context != NULL);
    CHECK(EVP_PKEY_verify_init(verify_context) > 0);
    CHECK(EVP_PKEY_CTX_set_rsa_padding(verify_context, RSA_PKCS1_PSS_PADDING) > 0);
    CHECK(EVP_PKEY_CTX_set_signature_md(verify_context, EVP_sha256()) > 0);
    CHECK(EVP_PKEY_CTX_set_rsa_mgf1_md(verify_context, EVP_sha256()) > 0);
    CHECK(EVP_PKEY_CTX_set_rsa_pss_saltlen(verify_context, 32) > 0);
    CHECK(EVP_PKEY_verify(verify_context, decoded, (size_t)decoded_len, digest, sizeof(digest)) == 1);
    EVP_PKEY_CTX_free(verify_context);
    EVP_PKEY_free(key);
    free(decoded);
    free(signature);
    puts("C SDK tests passed");
    return 0;
}
