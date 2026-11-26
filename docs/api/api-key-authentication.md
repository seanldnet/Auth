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
- licenses.replace
- users.read
- users.children.read
- quotas.read

普通 USER 不能创建 API Key。代理不能获得管理员全站权限，也不能调用额度分配、支付渠道、钱包管理、系统设置等管理员接口。

## 管理员 API Key

管理员密钥的 scope 固定为 *，可访问现有受保护的 /api/* 管理与业务接口（登录、注册、密码管理端点除外），包括用户、软件、授权、额度、钱包、支付订单、支付渠道、审计、区域节点和系统设置。服务端仍执行原控制器与 AdminMiddleware 的角色检查。

## 代理 API Key

Header：Authorization: Bearer ak_<64 位十六进制密钥>

- GET /api/licenses：仅查询本人签发授权；代理可用。
- POST /api/licenses：按本人额度签发授权；必须传 Idempotency-Key。
- POST /api/licenses/{licenseId}/renew：续费本人签发授权，需 licenses.renew 权限和 Idempotency-Key。
- POST /api/licenses/{licenseId}/replace：更换本人签发授权，需 licenses.replace 权限和 Idempotency-Key。
- GET /api/users：按当前代理角色查询其可见用户范围。
- GET /api/license-quotas：仅查询本人额度账户。
- GET /api/v1/licenses、POST /api/v1/licenses、POST /api/v1/licenses/{licenseId}/renew：兼容的版本化授权接口。
- GET /api/v1/users、GET /api/v1/users/children、GET /api/v1/quotas：版本化查询接口。

代理密钥不能访问 /api/admin/*、支付管理、钱包人工入账、系统设置、区域节点和审计管理接口。下级代理的数据范围仍由服务端按层级限制。

## 安全机制
- 所有 API Key 只保存 SHA-256 摘要。
- 支持到期时间、IP 白名单、权限范围、最后使用时间和撤销。
- 撤销后立即失效。
- API Key 通过 Authorization Bearer 头传递，不依赖浏览器 Cookie；管理接口继续执行原有服务端角色鉴权。
- v1 API 当前未实现独立请求签名、时间戳防重放和速率限制，生产环境接入前仍需补齐。
