# Auth 控制台侧边栏与角色菜单

所有页面都使用 AdminLayout 统一外壳。桌面端固定侧栏，移动端使用抽屉菜单。菜单按角色过滤；接口仍在服务端独立鉴权，隐藏菜单不作为权限控制。

| 分组 | 菜单 | 路由 | 可见角色 |
|---|---|---|---|
| 工作台 | 总览 | / | ADMIN / AGENT / SUB_AGENT / USER |
| 工作台 | 通知中心 | /notifications | 全部角色 |
| 授权业务 | 软件管理 | /apps | ADMIN |
| 授权业务 | 授权管理 | /licenses | ADMIN |
| 授权业务 | 我的授权 | /agent/licenses | AGENT / SUB_AGENT |
| 授权业务 | 额度分配 | /quotas | ADMIN |
| 授权业务 | 我的额度 | /agent/quotas | AGENT / SUB_AGENT |
| 用户与代理 | 用户管理 | /users | ADMIN / AGENT / SUB_AGENT |
| 用户与代理 | 账号信息 | /account | USER |
| 财务中心 | 我的钱包 | /wallet | 全部角色 |
| 财务中心 | 账户充值 | /top-up | 全部角色 |
| 财务中心 | 充值记录 | /payment-orders | 全部角色（管理员显示全站订单） |
| 财务中心 | 钱包管理 | /wallet-management | ADMIN |
| 财务中心 | 支付渠道 | /payments | ADMIN |
| 安全中心 | 签名密钥 | /security-keys | ADMIN |
| 安全中心 | API Key | /api-keys | ADMIN / AGENT / SUB_AGENT |
| 安全中心 | 操作审计 | /audit-logs | ADMIN |
| 开发者服务 | 开发者中心 | /developer-center | 全部角色 |
| 系统管理 | 区域节点 | /regions | ADMIN |
| 系统管理 | 系统监控 | /monitoring | ADMIN |
| 系统管理 | 系统设置 | /settings | ADMIN |

## 权限边界
- ADMIN：全站用户、软件、授权、额度、钱包、支付渠道、区域登记和系统设置。
- AGENT：管理自己的下级用户；查看本人额度；按额度签发/续费本人授权；不能向下级转额度。
- SUB_AGENT：管理自己的用户范围；查看本人额度；按额度签发/续费本人授权。
- USER：本人钱包、充值订单、账号信息和通知。
- API Key 权限由后端 ApiKeyMiddleware 再次检查，不能通过前端改路由绕过。
