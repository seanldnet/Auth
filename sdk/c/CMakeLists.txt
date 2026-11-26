cmake_minimum_required(VERSION 3.20)
project(auth_license_sdk_c VERSION 0.1.0 LANGUAGES C)

set(CMAKE_C_STANDARD 11)
set(CMAKE_C_STANDARD_REQUIRED ON)
set(CMAKE_C_EXTENSIONS ON)

find_package(OpenSSL 3.0 REQUIRED)
find_package(CURL REQUIRED)
find_package(PkgConfig REQUIRED)
pkg_check_modules(JSONC REQUIRED IMPORTED_TARGET json-c)

add_library(auth_license_sdk STATIC src/auth_license.c)
set_target_properties(auth_license_sdk PROPERTIES POSITION_INDEPENDENT_CODE ON)
target_include_directories(auth_license_sdk PUBLIC ${CMAKE_CURRENT_SOURCE_DIR}/include PRIVATE ${CMAKE_CURRENT_SOURCE_DIR}/src)
target_link_libraries(auth_license_sdk PUBLIC OpenSSL::Crypto CURL::libcurl PkgConfig::JSONC)
if(NOT WIN32)
  target_link_libraries(auth_license_sdk PUBLIC pthread)
endif()
target_compile_options(auth_license_sdk PRIVATE -Wall -Wextra -Wpedantic)

add_library(auth_license_easy_bridge SHARED src/easy_bridge.c)
target_include_directories(auth_license_easy_bridge PUBLIC ${CMAKE_CURRENT_SOURCE_DIR}/include)
target_link_libraries(auth_license_easy_bridge PRIVATE auth_license_sdk)
target_compile_definitions(auth_license_easy_bridge PRIVATE AUTH_EASY_BRIDGE_BUILD)
set_target_properties(auth_license_easy_bridge PROPERTIES OUTPUT_NAME "AuthLicenseEasyBridge" WINDOWS_EXPORT_ALL_SYMBOLS ON)



include(CTest)
if(BUILD_TESTING)
  add_executable(auth_license_sdk_tests tests/test_signing.c)
  target_include_directories(auth_license_sdk_tests PRIVATE ${CMAKE_CURRENT_SOURCE_DIR}/src)
  target_link_libraries(auth_license_sdk_tests PRIVATE auth_license_sdk OpenSSL::Crypto)
  add_test(NAME auth_license_sdk_tests COMMAND auth_license_sdk_tests)
  add_executable(auth_license_easy_bridge_tests tests/test_easy_bridge.c)
  target_link_libraries(auth_license_easy_bridge_tests PRIVATE auth_license_easy_bridge)
  add_test(NAME auth_license_easy_bridge_tests COMMAND auth_license_easy_bridge_tests)
endif()
