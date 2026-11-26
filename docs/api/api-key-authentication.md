# 管理员与代理 API Key

## 管理接口（会话鉴权）
- GET /api/keys：管理员查看全站 Key 元数据；代理/下级代理仅查看本人 Key。
- POST /api/keys：创建 Key。完整密钥只在创建响应中返回一次。
- DELETE /api/keys/{keyId}：撤销 Key；管理员可撤销全站 Key，代理只能撤销本人 Key。

创建请求字段：
- name：名称，最多 80 字。
- scopes：代理可选权限；管理员创建时服务端强制设为 *。
- ip_allowlist：可选 IP 数组；为空表示不限制 IP。
- expires_at：可选到期时间。

代理可用权限：
- licenses.create
- licenses.read
- licenses.renew
- users.read
- users.children.read
- quotas.read

普通 USER 不能创建 API Key。代理不能获得管理员全站权限，也不能调用额度分配、支付渠道、系统设置等接口。

## v1 API（Bearer API Key）
Header：Authorization: Bearer ak_<64 位十六进制密钥>

- GET /api/v1/licenses：查询授权。管理员必须传 software_id；代理仅查询本人签发的授权。
- POST /api/v1/licenses：管理员生成授权；代理/下级代理按本人额度生成授权，必须传 Idempotency-Key。
- POST /api/v1/licenses/{licenseId}/renew：续费本人签发的授权，需 licenses.renew 权限和 Idempotency-Key。
- GET /api/v1/users：按当前 API Key 所属角色的数据范围查询用户。
- GET /api/v1/users/children：查询本人下级范围用户。
- GET /api/v1/quotas：管理员查看全站额度；代理只查看本人额度。

所有 API Key 仅保存 SHA-256 摘要。支持到期时间、IP 白名单、权限范围、最后使用时间、撤销和审计记录。密钥撤销后立即失效。
