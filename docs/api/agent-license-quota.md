# 代理授权额度 API

## 规则
- 额度按「用户/代理 + 软件 + 套餐」分别核算。
- 只有管理员可以分配额度；代理和下级代理不能向任何下级转额度。
- 管理员只能将额度分配给 ACTIVE 的 AGENT 或 SUB_AGENT。
- 代理只能查询自己的额度账户，不能传入其他用户 ID 查询。
- 每次分配和消耗都写入不可变流水，并使用唯一幂等键防止重复入账。
- 代理生成授权码时，在同一数据库事务中锁定额度、生成授权码、写授权事件、扣减可用额度并写入消耗流水；任何一步失败都会整体回滚。

## 接口

### GET /api/license-quotas
登录用户查询额度。
- ADMIN：查看全站额度。
- AGENT / SUB_AGENT：仅查看自己的额度。
- 支持 page、page_size。

### POST /api/admin/license-quotas
仅管理员可调用。

请求字段：
- user_id：目标代理或下级代理 ID。
- software_id：软件 ID。
- plan_id：该软件下的套餐 ID。
- quantity：正整数，单次不超过 1,000,000。
- idempotency_key：8–160 位幂等键；也可通过 Idempotency-Key Header 传入。
- description：可选备注。

分配成功返回当前可用额度、累计分配和已消耗数量。

### POST /api/licenses
仅 AGENT / SUB_AGENT 可调用，按当前登录用户扣减额度。

请求 Header：
- Idempotency-Key：8–160 位唯一请求键；同一个键不能重复用于其他授权生成请求。

请求 JSON：
- software_id：软件 ID。
- plan_id：该软件下的套餐 ID。
- quantity：正整数，单次不超过 500。

成功返回本次生成的授权码（仅生成时返回明文）、生成数量和剩余可用额度。额度不足、软件/套餐停用、幂等键重复或授权生成失败时，不会产生部分授权或部分扣减。

## 数据表
- auth_agent_license_quotas：每个用户/软件/套餐一个额度账户。
- auth_agent_license_quota_transactions：管理员分配和授权生成消耗流水；流水不可变。
