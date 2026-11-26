# 授权更换 API

## 接口
- POST /api/admin/licenses/{licenseId}/replace：管理员更换任意符合条件的授权。
- POST /api/licenses/{licenseId}/replace：代理/下级代理更换本人签发的授权。
- POST /api/v1/licenses/{licenseId}/replace：API Key 方式更换授权；代理 Key 需要 licenses.replace 权限。

Header：
- Idempotency-Key：8–160 位唯一幂等键。

处理规则：
- 仅 GENERATED 或 ACTIVE 状态可更换。
- 原授权会变为 VOID，新授权生成新的随机授权码。
- 新授权继承原软件、套餐、持有人、状态、设备绑定、激活时间、到期时间和设备更换次数。
- 代理只能更换本人签发的授权。
- 如果原授权消耗了代理额度，系统在同一事务中将额度占用从旧授权转移到新授权，不额外扣减可用额度。
- 原授权作废、新授权创建、替换记录、事件和额度流水原子提交。
- 完整新授权码仅在本次成功响应中返回一次；数据库只保存 SHA-256 摘要。
- 冻结、作废和已过期授权不能直接更换；请先按业务规则恢复或续费。
