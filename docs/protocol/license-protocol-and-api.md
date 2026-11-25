# 授权协议与 API 规范（草案）

## 1. 目标

为 ThinkPHP 平台、多语言 SDK 和多种客户端定义统一、版本化、可测试的授权协议。本文为协议草案；在编码前必须完成字段、签名规范、错误码和兼容性评审。

## 2. 软件身份

- App ID：系统自动生成的软件公开标识。
- App Secret：敏感凭据，不得作为客户端内置长期秘密。
- Key ID：标识签名密钥版本。
- License ID：随机授权唯一标识。
- Device Binding：设备绑定标识/摘要；原始设备信息应最小化收集。

所有标识必须有明确作用域和唯一性约束。

## 3. 授权凭证字段建议

凭证可采用带数字签名的结构（例如经过标准化定义的 JWS 风格封装或明确版本化的签名信封）。字段建议：
- schema_version
- license_id
- app_id
- subject_id
- plan_id
- device_binding_hash
- issued_at
- not_before
- expires_at
- license_version
- key_id
- signature_algorithm

签名覆盖的字节序列、字段排序/规范化、编码、空值处理和时间单位必须固定并提供跨语言测试向量。SHA-256 是摘要算法；RSA-3072 是密钥规格。建议采用 RSA-PSS + SHA-256，最终协议评审后冻结。

## 4. 验证流程

1. 校验 TLS 和请求大小。
2. 校验 App ID、Key ID 与软件状态。
3. 校验请求签名、时间戳及 Nonce。
4. 校验请求权限与资源归属。
5. 查询授权状态和版本。
6. 校验授权有效期、软件归属及设备绑定。
7. 根据区域缓存/权威数据策略返回结果。
8. 成功验证写入聚合统计；异常验证写入完整审计。

客户端本地验签通过不等于服务端授权仍然有效；联网验证应检查撤销、冻结和设备状态。

## 5. API 资源规划

| 类别 | 操作 |
|---|---|
| 授权 | activate、verify、query、renew、replace、unbind、freeze、restore、revoke |
| 设备 | bind、change、list |
| 心跳 | heartbeat / lease refresh |
| 软件 | app identity、public key、key metadata |
| 代理管理 | user query、sub-agent query、license operations、quota query |
| 管理员 | 全站授权、用户、代理、额度及审计管理 |

最终 URL、HTTP 方法、字段、幂等规则和权限矩阵待 API 详细设计确认。

## 6. 请求安全

建议签名串至少绑定 HTTP 方法、规范化路径、规范化查询参数、时间戳、Nonce、请求体 SHA-256 摘要和 Key ID。使用 TLS。时间窗口和 Nonce 保留时间需配置并考虑跨区域时钟偏差。

## 7. 幂等与错误处理

激活、换机、续费、扣款等写操作必须有幂等键或业务唯一约束。重复请求应返回一致业务结果，不得重复扣款或重复消耗额度。错误码应稳定、可机器处理，并避免向客户端泄露内部堆栈或敏感信息。

## 8. 版本兼容

协议必须有 schema_version 和 API version。SDK 应声明支持的协议版本。密钥轮换期间通过 Key ID 选择验签公钥。旧协议退役需有公告、迁移窗口和调用统计。
