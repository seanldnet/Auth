using System.Security.Cryptography;
using Auth.LicenseSdk;
using Xunit;

namespace Auth.LicenseSdk.Tests;

public sealed class LicenseRequestSignerTests
{
    [Fact]
    public void CanonicalMessageUsesExactBodyDigestAndUppercaseMethod()
    {
        var body = System.Text.Encoding.UTF8.GetBytes("{\"app_id\":\"app_123\"}");
        var canonical = LicenseRequestSigner.BuildCanonicalMessage(
            "post", "/api/license/verify", "1795700000", "0123456789abcdef0123456789abcdef", body);

        Assert.StartsWith("POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n", canonical);
        Assert.EndsWith(Convert.ToHexString(SHA256.HashData(body)).ToLowerInvariant(), canonical);
    }

    [Fact]
    public void SignatureUsesRsaPssAndRejectsTampering()
    {
        using var rsa = RSA.Create(3072);
        var message = "POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n" + new string('a', 64);
        var signature = LicenseRequestSigner.Sign(rsa, message);

        Assert.True(rsa.VerifyData(System.Text.Encoding.UTF8.GetBytes(message), signature, HashAlgorithmName.SHA256, RSASignaturePadding.Pss));
        Assert.False(rsa.VerifyData(System.Text.Encoding.UTF8.GetBytes(message + "x"), signature, HashAlgorithmName.SHA256, RSASignaturePadding.Pss));
    }

    [Fact]
    public void CanonicalPathRejectsQueryString()
    {
        var body = Array.Empty<byte>();
        Assert.Throws<ArgumentException>(() => LicenseRequestSigner.BuildCanonicalMessage(
            "POST", "/api/license/verify?x=1", "1795700000", "0123456789abcdef", body));
    }
}
