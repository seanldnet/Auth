# Auth｜多软件授权管理平台

> **当前开发阶段：授权运行时、软件管理、基础用户管理、支付适配器及多语言 SDK 已有代码；代理额度/钱包财务、管理端 API 密钥权限体系、完整支付验收、全球双区域部署仍在开发或验证中。** 具体状态以[开发进度与验收台账](docs/development/project-status.md)为准。

## 项目目标
建设面向多个独立软件产品的授权管理平台，支持软件身份管理、授权签发与验证、设备绑定、代理体系、授权额度、余额与支付、API 接入、安全审计、全球区域化部署及开发者文档能力。

## 当前技术基线
- 后端：ThinkPHP 8 / PHP 8.2
- 前端：React + TypeScript
- UI：shadcn/ui（前端 UI 组件必须使用该体系，不自行仿制替代组件）
- 持久化：MySQL
- 缓存：Redis 集群（生产拓扑及容量仍需验证）
- 异步处理：消息队列（完整生产链路仍需建设/验证）
- 签名：RSA-3072 + RSA-PSS / SHA-256
- 全球目标：国内、海外分别部署独立授权集群，每个区域独立扩展至 80,000 QPS；规划流量比例各 50%。以上为验收目标，不代表当前已达成。

## 文档导航
- [需求规格 V1.3](docs/requirements/PRD-V1.3.md)
- [开发进度与验收台账](docs/development/project-status.md)
- [全球架构设计](docs/architecture/global-architecture.md)
- [安全与密钥管理](docs/security/security-and-key-management.md)
- [身份认证与会话安全](docs/security/authentication-and-session-security.md)
- [授权协议与 API 规范](docs/protocol/license-protocol-and-api.md)
- [软件 RSA-PSS 请求签名规范](docs/api/software-signature-protocol.md)
- [数据与日志设计](docs/data/data-and-logging.md)
- [开发规范与审计制度](docs/development/development-governance.md)
- [文档管理规范](docs/development/documentation-governance.md)
- [待确认事项](docs/requirements/open-decisions.md)
- [支付渠道与收银台架构](docs/architecture/payment-channel-architecture.md)
- [币种与支付渠道最终确认](docs/requirements/currency-and-payment-decisions.md)
- [前端 UI 与页面布局规范](docs/development/frontend-ui-standards.md)

## 状态约定
- **已实现（代码存在）**：仓库存在对应实现；不等同于集成测试或生产验收通过。
- **已测试**：有可追溯自动化测试或 CI 结果。
- **待联调/待验收**：需要真实数据库、商户、Windows IDE、部署环境或业务验收。
- **规划/未实现**：仅有需求或架构定义，尚无足够代码及测试证据。
- **待确认**：业务规则尚未得到最终确认，不得自行固化。

## 开发原则
1. 以 PRD V1.3 和已确认的补充规则为需求基线；开发进度必须同步到进度台账。
2. 代码变更按仓库开发治理规范通过 PR 审核后合并；不得伪造审核者或测试结果。
3. 每项代码变更记录文件路径、原因、关联需求、测试结果、Commit 和文档影响。
4. 性能目标必须通过分区域、持续压测验证，不得将规划指标描述为已达成能力。
5. 密钥、设备标识及个人信息按最小必要原则处理，跨区域数据同步须经过合规评估。
6. CNY 与 USDT 独立记账、独立展示，平台不提供汇率换算或币种互兑。
7. 支付渠道使用独立适配器；EPUSDT、GMPay 与 EPay 真实商户联调完成前不得标记为已接通。
8. 所有前端页面必须使用 shadcn/ui 体系并遵循统一留白、间距和响应式规范。
