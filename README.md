# Auth｜多软件授权管理平台

> 当前阶段：授权运行时、软件/套餐、用户与代理管理、授权生命周期、单用户钱包、支付适配器、API Key 和多语言 SDK 已有实现。CI 通过不等于真实支付联调、数据库迁移、跨区域部署或生产性能验收；详细状态以[开发进度与验收台账](docs/development/project-status.md)为准。

## 项目目标
建设面向多个独立软件产品的授权管理平台，支持软件身份管理、授权签发与验证、设备绑定、代理体系、授权额度、单一余额钱包、支付、API 接入、安全审计、区域化部署及开发者文档。

## 当前技术基线
- 后端：ThinkPHP 8 / PHP 8.2
- 前端：React + TypeScript
- UI：shadcn/ui
- 持久化：MySQL
- 缓存：Redis（生产拓扑和容量仍需验证）
- 异步处理：消息队列（完整生产链路仍需建设/验证）
- 软件授权签名：RSA-3072 + RSA-PSS / SHA-256
- API Key：SHA-256 Token 摘要；新 Key 支持 AES-256-GCM Secret 加密、HMAC-SHA256 请求签名及 Nonce 防重放
- 全球目标：国内、海外分别部署独立授权集群，每个区域 80,000 QPS；这是验收目标，不代表当前已达成

## 关键业务约束
- 每个用户只有一个钱包账户和一份数值余额。
- 管理员全局选择余额显示单位为 RMB（¥）或 U；切换只改变显示标签，不换算、不拆分、不改变余额数值。
- 支付订单可以保留支付渠道要求的币种、金额和交易号快照，用于网关验签与对账；这些字段不构成多钱包或多币种独立余额。
- 代理授权额度按软件/套餐隔离，由管理员分配；代理不能向下级代理转额度。
- 代理 API Key 只能调用授权及用户/下级查询等允许的接口；管理员权限仍由服务端中间件和控制器校验。

## Docker 一键部署\n\nUbuntu/Debian 服务器一键部署说明见 [deploy/README.md](deploy/README.md)。首次镜像发布并设置 GHCR 容器包为 Public 后，可执行：\n\n```bash\ncurl -fsSL https://raw.githubusercontent.com/seanldnet/Auth/main/deploy/install.sh | sudo bash\n```\n\n## 文档导航
- [需求规格 V1.3](docs/requirements/PRD-V1.3.md)
- [开发进度与验收台账](docs/development/project-status.md)
- [全球架构设计](docs/architecture/global-architecture.md)
- [安全与密钥管理](docs/security/security-and-key-management.md)
- [身份认证与会话安全](docs/security/authentication-and-session-security.md)
- [授权协议与 API 规范](docs/protocol/license-protocol-and-api.md)
- [软件 RSA-PSS 请求签名规范](docs/api/software-signature-protocol.md)
- [管理员/代理 API Key 认证规范](docs/api/api-key-authentication.md)
- [数据与日志设计](docs/data/data-and-logging.md)
- [开发规范与审计制度](docs/development/development-governance.md)
- [文档管理规范](docs/development/documentation-governance.md)
- [支付渠道与收银台架构](docs/architecture/payment-channel-architecture.md)
- [币种与支付渠道最终确认](docs/requirements/currency-and-payment-decisions.md)
- [前端 UI 与页面布局规范](docs/development/frontend-ui-standards.md)
- [待确认事项](docs/requirements/open-decisions.md)

## 状态约定
- **已实现（代码存在）**：仓库存在对应实现，不等同于集成测试或生产验收通过。
- **已测试**：有可追溯的自动化测试或 CI 结果。
- **待联调/待验收**：需要真实数据库、商户、部署环境或业务验收。
- **规划/未实现**：仅有需求或架构定义，尚无足够代码及测试证据。
- **待确认**：业务规则尚未得到最终确认，不得自行固化。

## 开发原则
1. 以 PRD V1.3 和已确认的补充规则为需求基线；进度必须同步到开发台账。
2. 代码变更通过 PR 和 CI；不得把未合并代码描述为主分支已交付。
3. 每项变更记录原因、文件路径、测试结果、Commit 和文档影响。
4. 性能目标必须通过分区域、持续压测验证，不得将规划指标描述为已达成能力。
5. 密钥、设备标识及个人信息按最小必要原则处理；跨区域同步须经过合规评估。
6. 支付渠道币种仅作为订单/渠道快照；钱包始终是单账户、单余额，显示单位全局统一且仅改变展示。
7. 支付渠道使用独立适配器；真实商户联调完成前不得标记为生产已接通。
8. 所有前端页面遵循既定 UI 组件、统一留白、间距和响应式规范。
