# 管理员钱包与人工充值

钱包模型：每个用户只有一个 auth_wallet_accounts.balance 数值。wallet_display_unit 是全局显示字段，只能为 CNY 或 U；切换不换算、不拆分、不改变数据库余额。

## 接口
- GET /api/admin/wallets?page=1&page_size=20&keyword=&role=：管理员查看用户钱包余额。
- POST /api/admin/wallets/{userId}/credit：管理员人工入账。

人工入账 Header：
- Idempotency-Key：8–160 位唯一请求键。

人工入账 JSON：
- amount：正数，最多 8 位小数。
- description：必填备注，最多 255 字。

处理规则：
- 余额行锁定后在同一事务中增加数值。
- 同时写入 auth_wallet_transactions（CREDIT / ADMIN_MANUAL_CREDIT）和管理员审计日志。
- 幂等键重复会拒绝，不会重复加款。
- 不存在钱包时先初始化单一钱包账户。
- 不保存货币列；界面显示单位读取全局 wallet_display_unit。
