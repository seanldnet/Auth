# 管理员与代理 API Key 请求认证规范

## 1. 凭据边界
- 管理员 API Key 可配置全站权限；代理/下级代理 API Key 只能配置允许的代理业务权限，服务端仍须按调用者的用户/下级数据范围过滤。
- API Key 与软件 App ID、软件 RSA-PSS 公钥完全分离，不得互相替代。
- Key ID 用于定位密钥；Secret 仅在创建时展示一次。数据库使用 AES-256-GCM 加密保存 Secret，主密钥从部署环境 `APP_KEY` 读取（至少 32 字符），不得提交到仓库。
- 撤销立即生效；密钥不得写入日志、审计 metadata 或普通 API 响应。

## 2. 创建与管理
- 登录用户可管理自己的 API Key；管理员可查看和撤销全站 API Key。
- 代理/下级代理仅可查看和撤销自己创建的 Key。
- 每个 Key 可设置名称、权限子集、精确 IP 白名单和可选过期时间。
- Secret 只在创建响应中返回一次；丢失后必须新建 Key 并撤销旧 Key。
- 创建/撤销必须写入 `auth_audit_logs`。

## 3. 签名请求
HTTPS 请求使用以下 Header：
- `X-API-Key-Id`：Key ID。
- `X-API-Timestamp`：Unix 秒时间戳。
- `X-API-Nonce`：16–64 位唯一随机值。
- `X-API-Signature`：小写/大写十六进制 HMAC-SHA256。

规范化签名原文为以下五行，以单个 LF 连接且末尾无换行：
```text
UPPERCASE_HTTP_METHOD
/normalized/request/path
UNIX_TIMESTAMP
NONCE
LOWERCASE_HEX_SHA256_OF_EXACT_BODY_BYTES
```
查询字符串不参与 path；请求体必须使用签名时的原始字节，不得先解析再重新序列化。签名为 `HMAC-SHA256(canonical_message, API_KEY_SECRET)`。

## 4. 重放与访问限制
- 服务端只接受与 UTC 当前时间偏差不超过 300 秒的请求。
- Nonce 在同一个 Key ID 范围内不可复用，至少保留 10 分钟；唯一索引负责并发防重放。
- 精确 IP 白名单非空时，仅允许列表内来源 IP。
- Key 必须处于 ACTIVE 且未过期；用户账号必须仍为 ACTIVE。
- 验签通过后仍须检查 Key 权限和资源归属。代理不得通过传入其他用户/代理 ID 越权访问。
- 定期清理过期 Nonce；清理任务不得删除仍在有效窗口内的记录。

## 5. 权限标识（首批）
- `license.create`：在调用者数据范围内开通授权。
- `license.replace`：在调用者数据范围内更换授权。
- `license.read`：查询调用者数据范围内授权。
- `user.read`：查询调用者数据范围内用户。
- `sub_agent.read`：查询调用者数据范围内下级代理。
- `*`：仅管理员可用，表示全站权限。

Key 管理接口首批：
- `GET /api/api-keys`
- `POST /api/api-keys`
- `PATCH /api/api-keys/{keyId}/revoke`

注意：Key 管理接口已先实现凭据生命周期；业务 API 的验签中间件、权限检查和授权/用户业务路由必须在后续开发中接入后，才能宣称第三方 API 调用闭环可用。
