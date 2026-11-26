# 开发进度与验收台账

- 盘点日期：2026-11-27
- 仓库：`seanldnet/Auth`
- 基线分支：`main`
- 盘点基线 Commit：`0e93b466989228309cf8e11285f5b540c00e6e02`
- 最新可见主分支 CI：[45859667009](https://github.com/seanldnet/Auth/actions/runs/45859667009)，结果 SUCCESS
- 说明：本文件记录仓库可见代码、目录、路由、数据库脚本、测试和 CI 证据。目录/类存在仅能证明代码已提交，不能单独证明功能端到端可用或生产验收通过。

## 1. 当前阶段结论

项目已超出“基础工程与支付适配器开发中”的旧阶段描述。授权运行时、软件目录/套餐、软件签名密钥、基础认证、用户管理及归属申请、支付订单/收银台/渠道适配器、多语言 SDK 均已有代码。

但完整业务闭环尚未完成。当前主要缺口集中在代理层级与权限、授权批量及生命周期补齐、额度/钱包/财务流水、管理员与代理 API 密钥体系、支付真实联调与对账、管理后台覆盖、生产级部署/性能验收。

## 2. 模块状态总览

| 模块 | 仓库证据 | 状态 | 尚需工作 |
|---|---|---|---|
| ThinkPHP 后端基础 | `backend/composer.json`，ThinkPHP 8.1 / PHP 8.2 | 已实现 | 部署配置、集成环境和生产验收 |
| 登录/会话/改密 | `Auth` Controller、AuthContext/AuthSessionService、AuthMiddleware；对应前端页面 | 已有实现 | 补齐安全边界与端到端测试 |
| 用户管理 | UserManagement Controller/Service、用户表扩展 | 部分实现 | 代理层级、角色数据范围、下级管理权限 |
| 用户归属申请 | UserTransfer Controller/Service、schema 006 | 已有实现 | 并发审批、权限边界、集成测试 |
| 软件目录/套餐 | SoftwareCatalog Controller/Service、对应 schema | 已有实现 | 完整配置校验及业务验收 |
| 软件 RSA-PSS 密钥 | SoftwareKeys、SoftwareSignatureVerifier、签名中间件、schema 007 | 已有实现 | MySQL 重放防护/唯一索引集成测试、轮换验收 |
| 授权运行时 | activate/verify/change-device/unbind 路由及 Runtime Service | 已有实现 | 生命周期边界、并发和数据库事务测试 |
| 授权管理 | LicenseManagement Controller/Service、生成/状态/解绑接口 | 部分实现 | 批量导入导出、续费、冻结恢复、审计及套餐额度闭环 |
| 支付核心 | 订单、回调、收银台、渠道管理及多个 Adapter/Signer | 部分实现 | 支付宝/微信官方渠道、商户真实联调、查单对账和异常补偿 |
| CNY/USDT | PaymentCurrency、支付架构与币种决策文档 | 已有基础实现 | 钱包、流水、跨模块账务一致性集成测试 |
| 代理额度/钱包/财务 | 当前 schema 001-007 未见额度账户、钱包账户、额度流水、余额流水的完整表组；当前应用目录未见对应独立服务 | 未完成核心闭环 | 设计并实现账本、订单、分配、扣减、冲正及幂等 |
| 管理员/代理 API Key | 当前路由未见管理端 API Key 管理及代理 API 访问入口 | 未完成 | 独立凭据模型、权限范围、IP 白名单、签名/Nonce/限流/审计 |
| 前端 | 登录、注册、仪表盘、账户、软件、授权、用户、充值、支付订单/支付等页面 | 部分实现 | 代理、额度、钱包、API Key、审计等页面；统一交互和响应式验收 |
| shadcn/ui | `frontend/components.json`；当前 `src/components/ui` 可见 badge/button/card/input | 组件覆盖不足 | 继续使用 shadcn/ui 扩展，不得自制替代体系 |
| SDK | `sdk/` 下 C#、C++、C、Go、Java、Kotlin、PHP、Python、Rust、TypeScript、易语言目录 | 多语言代码已提交 | 按语言验证构建/测试；易语言需真实 IDE 与 32/64 位联调 |
| 通知 | 当前路由/服务目录未见完整站内信、邮件、Telegram 通知闭环 | 未完成/待核验 | 通知模板、队列、重试、送达记录 |
| 全球双区域 | 架构文档已定义目标 | 规划/未验收 | 区域独立部署、消息同步、故障隔离及合规验证 |
| 性能目标 | PRD 定义每区域 80,000 QPS、24 小时及延迟目标 | 未验证 | 真实环境压测、容量报告和故障演练 |

## 3. 后端接口现状（从路由文件核对）

文件：`backend/route/app.php`

当前可见路由包括：
- 公共健康检查。
- 软件授权运行时：激活、验证、换机、解绑。
- 认证：登录、注册、登出、当前用户、改密。
- 用户：列表、创建、管理员分配、归属申请及审批、用户状态。
- 软件：管理员软件管理、软件签名密钥、套餐管理。
- 授权：管理员查询/生成、状态更新、解绑。
- 支付：渠道列表、订单列表/创建、渠道管理、异步回调、收银台和状态查询。

当前路由文件未见代理额度分配、钱包余额/流水、管理端 API Key CRUD、代理 API 授权管理等接口。这些应列为后续核心开发项，不能因已有用户/授权接口而视为完成。

## 4. 数据库脚本现状

当前可见 schema：
- `001_payment_core.sql`
- `002_auth_core.sql`
- `003_software_catalog.sql`
- `004_license_core.sql`
- `005_user_management.sql`
- `006_user_transfer_requests.sql`
- `007_software_signature_keys.sql`

从当前 schema 文件清单未见独立的代理额度账本、钱包余额/流水、管理端 API Key、通知、跨区域事件 Outbox/Inbox 等完整表组。新增模块必须先设计可迁移的表结构、唯一幂等键、外键/索引和事务边界，并提供升级/回滚说明。

## 5. SDK 当前目录

当前仓库可见 SDK 目录：`csharp`、`cpp`、`c`、`go`、`java`、`kotlin`、`php`、`python`、`rust`、`typescript`、`e-language`。

统一协议采用 RSA-PSS / SHA-256、时间戳、Nonce 和 HTTPS。SDK README 提供构建/使用说明。需要注意：
- SDK 目录和 README 不代表所有目标平台均已实机验收。
- 易语言 README 明确说明，C ABI 冒烟测试不能替代易语言 IDE 内 DLL 命令导入、UTF-8 转换及 32/64 位运行测试。
- CI 必须逐一核对 SDK workflow job；不得用某一次主分支成功概括所有 SDK 的完整平台覆盖。

## 6. CI 与质量证据

盘点时最新可见 main push：
- Run：45859667009
- Commit：`0e93b466`
- 内容：易语言授权 DLL bridge
- 结论：SUCCESS

近期历史中曾出现 Kotlin 测试、Rust SDK 相关失败，随后有修复提交并出现成功运行。应以每个 workflow run 的具体 job 为准。当前仍需补足：
1. MySQL 集成测试（唯一索引、事务、并发扣减/入账）。
2. 前端测试与关键流程 E2E。
3. Windows 原生 DLL 构建及易语言 IDE 实测。
4. 支付渠道沙箱/真实商户回调与主动查单。
5. 安全扫描、依赖漏洞和迁移回滚验证。

## 7. 下一阶段开发顺序

按业务闭环优先，不继续把 SDK 扩展作为当前主线：

### P0-A：代理与 API 身份权限
1. 梳理并实现管理员、代理、下级代理的数据范围规则。
2. 增加管理员/代理 API Key 独立管理；管理员密钥可按确认规则拥有全站权限，代理密钥仅可操作授权范围内资源。
3. API Key 哈希存储、只展示一次、禁用/轮换、IP 白名单、请求签名、防重放、限流及审计。
4. 为代理查询用户/下级、查询授权、开通授权、更换授权等接口加服务端数据范围校验；不得只依赖前端隐藏按钮。

### P0-B：额度与财务账本
1. 建立按软件/套餐/币种隔离的额度账户与不可变流水。
2. 建立 CNY、USDT 独立钱包及不可变余额流水。
3. 实现管理员额度分配、授权购买扣减、失败补偿和幂等。
4. 续费是否再次消耗额度按 PRD 已确认的软件级配置执行。
5. 所有账务变更使用数据库事务、行锁和唯一业务幂等键。

### P1：授权管理闭环
补齐批量生成/导入/导出、续费、冻结/恢复/作废、操作历史和到期提醒，并覆盖并发与权限测试。

### P1：前端管理后台
按既有 React + TypeScript + shadcn/ui 规范实现代理、API Key、额度、钱包、财务流水和审计页面；统一父容器间距、响应式 Grid、空/加载/错误状态。

### P1：支付闭环
补齐支付宝/微信官方适配器、商户沙箱、回调验签、主动查单、对账、异常队列与补偿；真实联调完成前保持“待联调”。

### P2：生产化与全球验收
部署、消息队列、跨区域数据分类同步、监控告警、灾备演练、分区域 80,000 QPS/24 小时压测。

## 8. 本轮文档同步记录

- 修正 README 的过时阶段描述。
- 增加本进度台账并链接到 README。
- 将“代码存在”“自动化测试通过”“真实联调/生产验收”分开标注。
- 后续每次模块完成、接口/表结构变更、CI 状态变化，必须更新本文件对应模块和证据。

## 9. 关键依据

- `docs/requirements/PRD-V1.3.md`
- `docs/architecture/payment-channel-architecture.md`
- `docs/requirements/currency-and-payment-decisions.md`
- `docs/api/software-signature-protocol.md`
- `docs/development/development-governance.md`
- `backend/route/app.php`
- `backend/database/schema/`
- `backend/tests/`
- `sdk/*/README.md`
