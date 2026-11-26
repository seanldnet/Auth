cmake_minimum_required(VERSION 3.20)
project(auth_license_sdk_c VERSION 0.1.0 LANGUAGES C)

set(CMAKE_C_STANDARD 11)
set(CMAKE_C_STANDARD_REQUIRED ON)
set(CMAKE_C_EXTENSIONS ON)

find_package(OpenSSL 3.0 REQUIRED)
find_package(CURL REQUIRED)
find_package(PkgConfig REQUIRED)
pkg_check_modules(JSONC REQUIRED IMPORTED_TARGET json-c)

add_library(auth_license_sdk src/auth_license.c)
target_include_directories(auth_license_sdk PUBLIC ${CMAKE_CURRENT_SOURCE_DIR}/include PRIVATE ${CMAKE_CURRENT_SOURCE_DIR}/src)
target_link_libraries(auth_license_sdk PUBLIC OpenSSL::Crypto CURL::libcurl PkgConfig::JSONC pthread)
target_compile_options(auth_license_sdk PRIVATE -Wall -Wextra -Wpedantic)

include(CTest)
if(BUILD_TESTING)
  add_executable(auth_license_sdk_tests tests/test_signing.c)
  target_include_directories(auth_license_sdk_tests PRIVATE ${CMAKE_CURRENT_SOURCE_DIR}/src)
  target_link_libraries(auth_license_sdk_tests PRIVATE auth_license_sdk OpenSSL::Crypto)
  add_test(NAME auth_license_sdk_tests COMMAND auth_license_sdk_tests)
endif()
