# 易语言授权 SDK（Windows DLL 调用桥接）

本目录提供易语言可调用的 Windows DLL C ABI 桥接层。桥接层复用已经通过 C SDK 测试的实现，不在易语言侧重复实现 RSA-PSS、Nonce 或请求规范化逻辑。

## 交付内容

- `AuthLicenseEasyBridge.dll`：Windows DLL 构建目标
- `auth_license_easy_bridge.h`：稳定的导出函数和参数定义
- `easy_bridge.c`：桥接实现
- `test_easy_bridge.c`：C ABI 冒烟测试

## DLL 函数

| 导出函数 | 用途 |
|---|---|
| `AuthLicense_Create` | 创建授权客户端句柄 |
| `AuthLicense_Activate` | 激活授权 |
| `AuthLicense_Verify` | 验证授权 |
| `AuthLicense_ChangeDevice` | 更换设备 |
| `AuthLicense_Unbind` | 解绑设备 |
| `AuthLicense_Destroy` | 释放客户端句柄 |
| `AuthLicense_FreeString` | 释放 SDK 返回的字符串 |

所有字符串参数均使用 UTF-8。Windows 导出函数使用 `__stdcall` 调用约定。句柄以不透明指针传递；易语言中使用整数型/长整数型变量保存 DLL 句柄时，必须匹配目标程序位数。

## 易语言 DLL 命令参数映射

在易语言的 DLL 命令定义中按以下类型映射：

- `const char *`：文本型指针，调用前转换为 UTF-8 字节串并确保以 0 结尾
- `AUTH_LICENSE_HANDLE`：指针/整数型句柄，32 位程序与 64 位程序必须匹配
- `long *http_status`：整数型输出参数指针（Windows ABI 的 C `long` 为 32 位）
- `char **error_message`：文本指针输出参数；非空时必须调用 `AuthLicense_FreeString`
- `char *` 返回值：JSON UTF-8 字符串；使用后必须调用 `AuthLicense_FreeString`

建议在易语言封装模块中统一处理 UTF-8 转换、指针读写、错误释放和句柄生命周期，避免每个业务窗口重复处理。

## 编译

在 Windows x64 环境使用 Visual Studio 2022、CMake 3.20+、OpenSSL 3、libcurl 和 json-c：

```bash
cmake -S sdk/c -B build -A x64
cmake --build build --config Release
```

生成的 DLL 位于 CMake 对应配置输出目录，名称为 `AuthLicenseEasyBridge.dll`。

## 安全约束

- 只把 RSA 公钥上传到平台；私钥保存在客户端受保护的位置。
- 必须使用 HTTPS；桥接层沿用 C SDK 的证书校验和禁止重定向设置。
- 不要把私钥、授权码或完整签名写入普通日志。
- DLL 位数必须与易语言程序位数一致。
- 当前 CI 会构建并测试 C ABI 桥接接口；易语言 IDE 内的 DLL 命令导入、UTF-8 转换和 32/64 位运行仍需在对应 Windows 易语言环境做最终实测，不能把 C ABI 测试当成易语言 IDE 联调通过。
