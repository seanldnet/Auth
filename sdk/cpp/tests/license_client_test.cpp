#include "auth_license/license_client.hpp"

#include <cassert>
#include <iostream>
#include <openssl/rsa.h>
#include <openssl/sha.h>

int main() {
    const std::string body = R"({"app_id":"app_123"})";
    const auto canonical = auth_license::LicenseClient::build_canonical_message(
        "post", "/api/license/verify", "1795700000",
        "0123456789abcdef0123456789abcdef", body);
    assert(canonical.rfind("POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n", 0) == 0);

    bool rejected_query = false;
    try {
        (void)auth_license::LicenseClient::build_canonical_message(
            "POST", "/api/license/verify?x=1", "1", "0123456789abcdef", "");
    } catch (const std::invalid_argument&) {
        rejected_query = true;
    }
    assert(rejected_query);

    EVP_PKEY_CTX* key_context = EVP_PKEY_CTX_new_from_name(nullptr, "RSA", nullptr);
    assert(key_context != nullptr);
    assert(EVP_PKEY_keygen_init(key_context) > 0);
    assert(EVP_PKEY_CTX_set_rsa_keygen_bits(key_context, 3072) > 0);
    EVP_PKEY* key = nullptr;
    assert(EVP_PKEY_keygen(key_context, &key) > 0);
    EVP_PKEY_CTX_free(key_context);

    const std::string message = "signed request";
    const std::string encoded = auth_license::LicenseClient::sign_rsa_pss_sha256(key, message);
    std::string decoded((encoded.size() * 3) / 4, '\0');
    const int decoded_size = EVP_DecodeBlock(reinterpret_cast<unsigned char*>(decoded.data()),
                                             reinterpret_cast<const unsigned char*>(encoded.data()),
                                             static_cast<int>(encoded.size()));
    assert(decoded_size > 0);
    const std::size_t padding = encoded.size() >= 2 && encoded.compare(encoded.size() - 2, 2, "==") == 0 ? 2
        : (!encoded.empty() && encoded.back() == '=' ? 1 : 0);
    decoded.resize(static_cast<std::size_t>(decoded_size) - padding);

    unsigned char digest[SHA256_DIGEST_LENGTH];
    SHA256(reinterpret_cast<const unsigned char*>(message.data()), message.size(), digest);
    EVP_PKEY_CTX* verify_context = EVP_PKEY_CTX_new(key, nullptr);
    assert(verify_context != nullptr);
    assert(EVP_PKEY_verify_init(verify_context) > 0);
    assert(EVP_PKEY_CTX_set_rsa_padding(verify_context, RSA_PKCS1_PSS_PADDING) > 0);
    assert(EVP_PKEY_CTX_set_signature_md(verify_context, EVP_sha256()) > 0);
    assert(EVP_PKEY_CTX_set_rsa_mgf1_md(verify_context, EVP_sha256()) > 0);
    assert(EVP_PKEY_CTX_set_rsa_pss_saltlen(verify_context, 32) > 0);
    assert(EVP_PKEY_verify(verify_context,
        reinterpret_cast<const unsigned char*>(decoded.data()), decoded.size(), digest, sizeof(digest)) == 1);
    EVP_PKEY_CTX_free(verify_context);
    EVP_PKEY_free(key);

    std::cout << "C++ SDK tests passed\n";
    return 0;
}
