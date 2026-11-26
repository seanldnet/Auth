#pragma once

#include <memory>
#include <stdexcept>
#include <string>
#include <nlohmann/json.hpp>
#include <openssl/evp.h>

namespace auth_license {

class LicenseApiError final : public std::runtime_error {
public:
    LicenseApiError(long status_code, const std::string& message);
    long status_code() const noexcept { return status_code_; }
private:
    long status_code_;
};

class LicenseClient final {
public:
    LicenseClient(std::string base_url, std::string app_id, std::string key_id, const std::string& private_key_pem);
    ~LicenseClient();

    LicenseClient(const LicenseClient&) = delete;
    LicenseClient& operator=(const LicenseClient&) = delete;
    LicenseClient(LicenseClient&&) = delete;
    LicenseClient& operator=(LicenseClient&&) = delete;

    nlohmann::json activate(const std::string& license_key, const std::string& device_fingerprint,
                            const std::string& device_label = "");
    nlohmann::json verify(const std::string& license_key, const std::string& device_fingerprint);
    nlohmann::json change_device(const std::string& license_key, const std::string& current_fingerprint,
                                 const std::string& new_fingerprint, const std::string& device_label = "");
    nlohmann::json unbind(const std::string& license_key, const std::string& device_fingerprint);

    static std::string build_canonical_message(const std::string& method, const std::string& path,
                                               const std::string& timestamp, const std::string& nonce,
                                               const std::string& body);
    static std::string sign_rsa_pss_sha256(EVP_PKEY* private_key, const std::string& canonical_message);

private:
    nlohmann::json post(const std::string& path, const nlohmann::json& payload);
    std::string base_url_;
    std::string app_id_;
    std::string key_id_;
    EVP_PKEY* private_key_{nullptr};
};

} // namespace auth_license
