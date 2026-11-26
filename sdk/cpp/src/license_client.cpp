#include "auth_license/license_client.hpp"

#include <algorithm>
#include <chrono>
#include <cctype>
#include <cstdint>
#include <mutex>
#include <regex>
#include <vector>
#include <utility>

#include <curl/curl.h>
#include <openssl/bio.h>
#include <openssl/pem.h>
#include <openssl/rand.h>
#include <openssl/rsa.h>
#include <openssl/err.h>
#include <openssl/sha.h>

namespace auth_license {
namespace {
constexpr std::size_t kMaxResponseBytes = 1024 * 1024;

void ensure_curl_initialized() {
    static std::once_flag once;
    std::call_once(once, [] {
        if (curl_global_init(CURL_GLOBAL_DEFAULT) != CURLE_OK) {
            throw std::runtime_error("Unable to initialize libcurl");
        }
    });
}

std::string hex_encode(const unsigned char* data, std::size_t size) {
    static constexpr char digits[] = "0123456789abcdef";
    std::string output(size * 2, '0');
    for (std::size_t i = 0; i < size; ++i) {
        output[i * 2] = digits[data[i] >> 4];
        output[i * 2 + 1] = digits[data[i] & 0x0f];
    }
    return output;
}

std::string random_nonce() {
    unsigned char bytes[32];
    if (RAND_bytes(bytes, sizeof(bytes)) != 1) {
        throw std::runtime_error("Unable to generate request nonce");
    }
    return hex_encode(bytes, sizeof(bytes));
}

std::size_t append_response(char* data, std::size_t size, std::size_t count, void* context) {
    const std::size_t bytes = size * count;
    auto* output = static_cast<std::string*>(context);
    if (output->size() + bytes > kMaxResponseBytes) return 0;
    output->append(data, bytes);
    return bytes;
}

std::string openssl_error_message(const char* fallback) {
    const unsigned long code = ERR_get_error();
    if (code == 0) return fallback;
    char buffer[256]{};
    ERR_error_string_n(code, buffer, sizeof(buffer));
    return std::string(fallback) + ": " + buffer;
}

void require_openssl(int result, const char* operation) {
    if (result <= 0) throw std::runtime_error(openssl_error_message(operation));
}
} // namespace

LicenseApiError::LicenseApiError(long status_code, const std::string& message)
    : std::runtime_error(message), status_code_(status_code) {}

LicenseClient::LicenseClient(std::string base_url, std::string app_id, std::string key_id,
                             const std::string& private_key_pem)
    : base_url_(std::move(base_url)), app_id_(std::move(app_id)), key_id_(std::move(key_id)) {
    if (!std::regex_match(app_id_, std::regex(R"(app_[a-fA-F0-9]{24})"))) {
        throw std::invalid_argument("A valid software App ID is required");
    }
    if (!std::regex_match(key_id_, std::regex(R"(key_[a-f0-9]{16})"))) {
        throw std::invalid_argument("A valid signing Key ID is required");
    }
    if (!std::regex_match(base_url_, std::regex(R"(https://[^/?#]+/?)"))) {
        throw std::invalid_argument("The API base URL must be an absolute HTTPS origin");
    }
    while (!base_url_.empty() && base_url_.back() == '/') base_url_.pop_back();

    BIO* bio = BIO_new_mem_buf(private_key_pem.data(), static_cast<int>(private_key_pem.size()));
    if (bio == nullptr) throw std::runtime_error(openssl_error_message("Unable to read private key PEM"));
    private_key_ = PEM_read_bio_PrivateKey(bio, nullptr, nullptr, nullptr);
    BIO_free(bio);
    if (private_key_ == nullptr || EVP_PKEY_base_id(private_key_) != EVP_PKEY_RSA
        || EVP_PKEY_get_bits(private_key_) < 3072) {
        if (private_key_ != nullptr) EVP_PKEY_free(private_key_);
        private_key_ = nullptr;
        throw std::invalid_argument("RSA-3072 or stronger private key is required");
    }
    ensure_curl_initialized();
}

LicenseClient::~LicenseClient() {
    if (private_key_ != nullptr) EVP_PKEY_free(private_key_);
}

std::string LicenseClient::build_canonical_message(const std::string& method, const std::string& path,
                                                     const std::string& timestamp, const std::string& nonce,
                                                     const std::string& body) {
    if (path.empty() || path.front() != '/' || path.find('?') != std::string::npos) {
        throw std::invalid_argument("Signed path must start with / and must not contain a query string");
    }
    unsigned char digest[SHA256_DIGEST_LENGTH];
    SHA256(reinterpret_cast<const unsigned char*>(body.data()), body.size(), digest);
    std::string upper_method = method;
    std::transform(upper_method.begin(), upper_method.end(), upper_method.begin(),
                   [](unsigned char c) { return static_cast<char>(std::toupper(c)); });
    return upper_method + "\n" + path + "\n" + timestamp + "\n" + nonce + "\n"
        + hex_encode(digest, sizeof(digest));
}

std::string LicenseClient::sign_rsa_pss_sha256(EVP_PKEY* private_key, const std::string& message) {
    unsigned char digest[SHA256_DIGEST_LENGTH];
    SHA256(reinterpret_cast<const unsigned char*>(message.data()), message.size(), digest);

    EVP_PKEY_CTX* context = EVP_PKEY_CTX_new_from_pkey(nullptr, private_key, nullptr);
    if (context == nullptr) throw std::runtime_error(openssl_error_message("Unable to allocate RSA-PSS context"));
    std::size_t signature_size = 0;
    std::vector<unsigned char> signature;
    try {
        require_openssl(EVP_PKEY_sign_init(context), "RSA-PSS sign init failed");
        require_openssl(EVP_PKEY_CTX_set_rsa_padding(context, RSA_PKCS1_PSS_PADDING), "RSA-PSS padding setup failed");
        require_openssl(EVP_PKEY_CTX_set_signature_md(context, EVP_sha256()), "RSA-PSS digest setup failed");
        require_openssl(EVP_PKEY_CTX_set_rsa_mgf1_md(context, EVP_sha256()), "RSA-PSS MGF1 setup failed");
        require_openssl(EVP_PKEY_CTX_set_rsa_pss_saltlen(context, 32), "RSA-PSS salt length setup failed");
        require_openssl(EVP_PKEY_sign(context, nullptr, &signature_size, digest, sizeof(digest)), "RSA-PSS signature sizing failed");
        signature.resize(signature_size);
        require_openssl(EVP_PKEY_sign(context, signature.data(), &signature_size, digest, sizeof(digest)), "RSA-PSS signing failed");
        signature.resize(signature_size);
    } catch (...) {
        EVP_PKEY_CTX_free(context);
        throw;
    }
    EVP_PKEY_CTX_free(context);

    std::string encoded(4 * ((signature.size() + 2) / 3), '\\0');
    const int encoded_size = EVP_EncodeBlock(reinterpret_cast<unsigned char*>(encoded.data()),
                                             signature.data(), static_cast<int>(signature.size()));
    if (encoded_size < 0) throw std::runtime_error("Unable to Base64-encode RSA-PSS signature");
    encoded.resize(static_cast<std::size_t>(encoded_size));
    return encoded;
}

nlohmann::json LicenseClient::activate(const std::string& license_key, const std::string& fingerprint,
                                       const std::string& label) {
    nlohmann::json payload{{"app_id", app_id_}, {"license_key", license_key},
                           {"device_fingerprint", fingerprint}, {"device_label", label}};
    return post("/api/license/activate", payload);
}

nlohmann::json LicenseClient::verify(const std::string& license_key, const std::string& fingerprint) {
    return post("/api/license/verify", {{"app_id", app_id_}, {"license_key", license_key},
                                       {"device_fingerprint", fingerprint}});
}

nlohmann::json LicenseClient::change_device(const std::string& license_key, const std::string& current_fingerprint,
                                            const std::string& new_fingerprint, const std::string& label) {
    return post("/api/license/change-device", {{"app_id", app_id_}, {"license_key", license_key},
        {"current_device_fingerprint", current_fingerprint}, {"new_device_fingerprint", new_fingerprint},
        {"device_label", label}});
}

nlohmann::json LicenseClient::unbind(const std::string& license_key, const std::string& fingerprint) {
    return post("/api/license/unbind", {{"app_id", app_id_}, {"license_key", license_key},
                                       {"device_fingerprint", fingerprint}});
}

nlohmann::json LicenseClient::post(const std::string& path, const nlohmann::json& payload) {
    const std::string body = payload.dump(-1, ' ', false, nlohmann::json::error_handler_t::strict);
    const auto timestamp = std::to_string(std::chrono::duration_cast<std::chrono::seconds>(
        std::chrono::system_clock::now().time_since_epoch()).count());
    const auto nonce = random_nonce();
    const auto canonical = build_canonical_message("POST", path, timestamp, nonce, body);
    const auto signature = sign_rsa_pss_sha256(private_key_, canonical);

    struct curl_slist* headers = nullptr;
    const std::vector<std::string> header_values = {
        "Accept: application/json", "Content-Type: application/json",
        "X-App-Id: " + app_id_, "X-Key-Id: " + key_id_,
        "X-Timestamp: " + timestamp, "X-Nonce: " + nonce, "X-Signature: " + signature
    };
    for (const auto& value : header_values) headers = curl_slist_append(headers, value.c_str());

    CURL* curl = curl_easy_init();
    if (curl == nullptr) {
        curl_slist_free_all(headers);
        throw std::runtime_error("Unable to initialize HTTP request");
    }
    std::string response_body;
    long status_code = 0;
    curl_easy_setopt(curl, CURLOPT_URL, (base_url_ + path).c_str());
    curl_easy_setopt(curl, CURLOPT_POST, 1L);
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS, body.data());
    curl_easy_setopt(curl, CURLOPT_POSTFIELDSIZE_LARGE, static_cast<curl_off_t>(body.size()));
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, append_response);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &response_body);
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 5L);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, 10L);
    curl_easy_setopt(curl, CURLOPT_SSL_VERIFYPEER, 1L);
    curl_easy_setopt(curl, CURLOPT_SSL_VERIFYHOST, 2L);
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 0L);
    const CURLcode result = curl_easy_perform(curl);
    curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &status_code);
    curl_easy_cleanup(curl);
    curl_slist_free_all(headers);
    if (result != CURLE_OK) throw std::runtime_error(std::string("License API transport failed: ") + curl_easy_strerror(result));

    nlohmann::json envelope;
    try { envelope = nlohmann::json::parse(response_body); }
    catch (const nlohmann::json::exception&) { throw LicenseApiError(status_code, "License API returned invalid JSON"); }
    if (status_code < 200 || status_code >= 300 || !envelope.value("success", false)
        || !envelope.contains("data") || !envelope["data"].is_object()) {
        throw LicenseApiError(status_code, envelope.value("message", std::string("License API request failed")));
    }
    return envelope["data"];
}

} // namespace auth_license
