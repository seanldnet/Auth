# 管理员与代理 API Key

## 管理接口（会话鉴权）
- GET /api/keys：管理员查看全站 Key 元数据；代理/下级代理仅查看本人 Key。
- POST /api/keys：创建 Key。完整密钥只在创建响应中返回一次。
- DELETE /api/keys/{keyId}：撤销 Key；管理员可撤销全站 Key，代理只能撤销本人 Key。

创建请求字段：name、scopes、ip_allowlist、expires_at。管理员的 scope 由服务端固定为 `*`；代理只能选择授权、用户/下级和额度只读/授权操作范围。普通 USER 不能创建 API Key。

## 兼容的 Bearer 认证
已有客户端可继续使用：

```http
Authorization: Bearer ak_<64 位十六进制密钥>
```

该方式保持向后兼容。新创建的 Key 同时支持下面的签名方式；历史 Key 未保存可恢复的 Secret，因此仍使用 Bearer 方式，建议逐步轮换为新 Key。

## HMAC-SHA256 签名认证（推荐）
签名请求使用 HTTPS，并发送以下 Header：

- `X-API-Key-Id`：创建响应中 `key_prefix` 的值（15 个字符）。
- `X-API-Timestamp`：Unix 秒时间戳，服务端允许的时钟偏差最多 300 秒。
- `X-API-Nonce`：每次请求随机生成的唯一值，16–64 位，仅允许字母、数字、下划线和连字符。
- `X-API-Signature`：规范化请求的 HMAC-SHA256 十六进制签名。

创建响应中的 `api_key` 仍是兼容 Bearer Token。签名 Secret 为该值去掉前 15 个字符后的剩余 52 个十六进制字符；Secret 只在创建响应中出现一次，必须安全保存。服务端以 AES-256-GCM 加密保存 Secret，密钥来自部署环境变量 `APP_KEY`（至少 32 字符），数据库同时只保存 Bearer Token 的 SHA-256 摘要用于旧协议兼容。

规范化原文按以下五行以 LF 连接，末尾不加换行：

```text
UPPERCASE_HTTP_METHOD
/normalized/request/path
UNIX_TIMESTAMP
NONCE
LOWERCASE_HEX_SHA256_OF_EXACT_BODY_BYTES
```

签名计算：`HMAC-SHA256(canonical_message, API_SECRET)`。查询字符串不参与 path；请求体必须使用实际发送的原始字节，不得先解析后重新序列化。GET 请求的 body 为空字符串。

## 防重放与权限
- 时间戳超出 300 秒窗口拒绝。
- Nonce 在单个 API Key 范围内唯一，服务端通过唯一索引原子写入，重复请求返回 HTTP 409；Nonce 保留至少 10 分钟。
- Key 必须未撤销、未过期，所属用户必须处于 ACTIVE。
- 精确 IP 白名单非空时，来源 IP 必须匹配。
- 验签后继续执行原有 scope、角色及资源归属检查；签名不能扩大代理的数据范围。
- 管理员 `*` 仍经过控制器/AdminMiddleware 的角色校验；代理 Key 不能访问管理员管理接口。

## 当前实现边界
- 新创建的 API Key 支持 HMAC 签名和 Nonce 防重放。
- 旧 Key 继续支持 Bearer；迁移后创建的新 Key 同时保留 Bearer 兼容能力。
- 尚未实现按 Key/IP 的请求速率限制和 Nonce 清理定时任务；生产环境仍需配置清理任务及网关级限流。
- 数据库升级脚本：`backend/database/schema/016_api_key_request_signatures.sql`。
