#ifndef AUTH_LICENSE_SDK_INTERNAL_H
#define AUTH_LICENSE_SDK_INTERNAL_H
#include <openssl/evp.h>
#include <stddef.h>
char *auth_license_build_canonical_message(const char *method, const char *path,
    const char *timestamp, const char *nonce, const unsigned char *body, size_t body_length);
char *auth_license_sign_rsa_pss_sha256(EVP_PKEY *private_key, const char *message);
#endif
