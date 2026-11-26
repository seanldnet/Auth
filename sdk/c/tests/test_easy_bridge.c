#include "auth_license_easy_bridge.h"
#include <stdio.h>

#define CHECK(condition) do { if (!(condition)) { fprintf(stderr, "check failed: %s\n", #condition); return 1; } } while (0)

int main(void) {
    char *error = NULL;
    AUTH_LICENSE_HANDLE handle = AuthLicense_Create(
        "http://license.example.com",
        "app_0123456789abcdef01234567",
        "key_0123456789abcdef",
        "not-a-key",
        10,
        &error);
    CHECK(handle == NULL);
    CHECK(error != NULL);
    AuthLicense_FreeString(error);
    AuthLicense_Destroy(NULL);
    puts("易语言 DLL bridge ABI tests passed");
    return 0;
}
