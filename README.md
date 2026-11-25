# Auth｜多软件授权管理平台

> 项目阶段：基础工程与支付适配器开发中。业务模块、真实商户联调和性能指标仍需逐项完成与验证。

## 项目目标
建设面向多软件、多租户及代理体系的授权管理平台，支持全球国内/海外独立授权集群、设备绑定、额度与财务管理、非对称数字签名、密钥生命周期管理及完整开发审计。

## 当前基线
- 后端：ThinkPHP
- 前端：React + TypeScript
- UI：shadcn/ui（前端 UI 组件必须使用该体系，不自行仿制替代组件）
- 持久化：MySQL
- 缓存：Redis 集群
- 异步处理：消息队列
- 签名：RSA-3072 + SHA-256；签名封装及填充规范见安全协议文档
- 全球目标：国内、海外分别部署独立授权集群，每个区域独立扩展至 80,000 QPS；规划流量比例各 50%

## 文档导航
- [需求规格 V1.3](docs/requirements/PRD-V1.3.md)
- [全球架构设计](docs/architecture/global-architecture.md)
- [安全与密钥管理](docs/security/security-and-key-management.md)
- [授权协议与 API 规范](docs/protocol/license-protocol-and-api.md)
- [数据与日志设计](docs/data/data-and-logging.md)
- [开发规范与审计制度](docs/development/development-governance.md)
- [文档管理规范](docs/development/documentation-governance.md)
- [待确认事项](docs/requirements/open-decisions.md)
- [支付渠道与收银台架构](docs/architecture/payment-channel-architecture.md)
- [币种与支付渠道最终确认](docs/requirements/currency-and-payment-decisions.md)
- [前端 UI 与页面布局规范](docs/development/frontend-ui-standards.md)

## 状态约定
- **已确认**：用户明确确认的需求或技术约束。
- **建议方案**：当前架构建议，尚未视为用户最终确认。
- **待确认**：需要进一步决策，不能擅自实现为既定规则。
- **未实现**：仅有文档定义，尚无代码或运行环境证明。

## 开发原则
1. 需求基线已进入实施阶段；业务代码按模块开发、测试和审查。
2. 代码变更必须通过 Pull Request 审核后才能合并；不得直接绕过审核修改主分支。
3. 每项代码变更必须关联需求/缺陷、Git Commit、测试结果和文档影响。
4. 性能目标必须通过分区域、持续压测验证，不得将规划指标描述为已达成能力。
5. 密钥、设备标识及个人信息按最小必要原则处理，跨区域数据同步须经过合规评估。
6. CNY 与 USDT 独立记账、独立展示，平台不提供汇率换算或币种互兑。
7. 支付渠道使用独立适配器；EPUSDT、GMPay 与 EPay 收银台按官方协议实现，真实商户联调完成前不得标记为已接通。
8. 所有前端页面必须使用 shadcn/ui 体系并遵循统一留白、间距和响应式规范。
