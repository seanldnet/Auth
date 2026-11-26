cmake_minimum_required(VERSION 3.20)
project(auth_license_sdk VERSION 0.1.0 LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_CXX_EXTENSIONS OFF)

find_package(OpenSSL 3.0 REQUIRED)
find_package(CURL REQUIRED)
find_package(nlohmann_json 3.10 REQUIRED)

add_library(auth_license_sdk src/license_client.cpp)
target_include_directories(auth_license_sdk PUBLIC ${CMAKE_CURRENT_SOURCE_DIR}/include)
target_link_libraries(auth_license_sdk PUBLIC OpenSSL::Crypto CURL::libcurl nlohmann_json::nlohmann_json)
target_compile_options(auth_license_sdk PRIVATE -Wall -Wextra -Wpedantic)

include(CTest)
if(BUILD_TESTING)
  add_executable(auth_license_sdk_tests tests/license_client_test.cpp)
  target_link_libraries(auth_license_sdk_tests PRIVATE auth_license_sdk OpenSSL::Crypto)
  add_test(NAME auth_license_sdk_tests COMMAND auth_license_sdk_tests)
endif()
