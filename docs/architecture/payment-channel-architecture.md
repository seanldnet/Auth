# 支付渠道与收银台架构

## 1. 结算币种

平台支持 CNY 与 USDT 两种独立记账币种：
- CNY 展示为人民币，例如 `¥100.00`。
- USDT 展示为 U，例如 `100 U`。
- 两种币种独立余额、独立订单、独立价格配置、独立流水；禁止汇率换算、自动兑换或跨币种抵扣。
- 订单创建时固化币种和应付金额。支付渠道必须声明其支持币种；不匹配时拒绝创建支付单。
- 账务使用币种最小单位整数或精确十进制类型，禁止浮点运算。具体精度由币种与渠道配置共同约束，须与支付商户协议一致。

## 2. 渠道范围

适配器注册表需支持：
1. 支付宝官方支付接口（CNY）。
2. 微信支付官方支付接口（CNY）。
3. 易支付（按商户配置的币种和支付产品）。
4. EPUSDT（USDT）。
5. GMPAY（按商户正式开通的币种和产品）。

EPUSDT 与 GMPAY 必须支持商户文档明确提供的收银台模式：创建支付订单、返回收银台 URL 或官方要求的表单/跳转参数、用户跳转支付、异步通知、主动查单和关闭订单。实际字段、签名算法、回调规范和支付状态映射必须以商户正式 API 文档为准，不得猜测。

## 3. 统一支付生命周期

`CREATED -> PENDING -> SUCCEEDED | FAILED | CLOSED | EXPIRED`

- 创建订单时生成平台唯一订单号和幂等键。
- 向渠道下单后保存渠道订单号、币种、金额、渠道代码、请求摘要及过期时间。
- 收银台 URL/表单必须经后端返回；不得在浏览器中暴露商户密钥。
- 前端跳转只使用后端返回且通过校验的支付地址；禁止开放重定向。
- 浏览器同步跳转仅用于展示结果，不作为到账依据。
- 只有服务端验签通过的异步通知或可信查单结果，才能将订单置为成功。
- 回调必须验证商户身份、签名、订单号、金额、币种、商户订单状态及渠道状态。
- 回调处理与余额入账在同一数据库事务内执行，并以渠道交易号和平台订单号双重幂等。
- 重复通知返回渠道要求的成功应答，但不得重复入账。
- 对账任务定期查单，发现支付成功但回调丢失时补偿入账；金额或币种不一致进入人工异常队列。
- 原始通知、验签结果、状态变更和账务流水均需审计留痕，敏感字段脱敏。

## 4. 收银台模式

- 支持跳转 URL、POST 表单或渠道官方定义的收银台交互类型，由适配器声明，不做统一假设。
- 收银台创建接口必须返回支付订单 ID、渠道、币种、金额、过期时间和安全跳转信息。
- 支付页展示渠道名称、订单金额、币种、订单有效期及支付状态。
- 用户关闭页面或返回商户页面，不代表支付失败；前端应轮询平台订单状态或等待服务端通知。
- 收银台页面不得接收或存储渠道私钥、API Secret、商户证书私钥。
- 支付链接应校验 HTTPS、域名白名单和有效期；不得将任意用户输入 URL 直接跳转。

## 5. 管理后台配置

每个支付渠道独立配置启用状态、展示名称、支持币种、支付产品/方式、商户号、API 地址、回调地址、同步返回地址、凭据引用、收银台模式、订单超时、查单策略、回调重试、渠道权重、适用代理范围及金额限额。实际凭据字段以渠道文档为准。凭据必须加密存储或使用密钥管理服务引用，页面只显示掩码，不回显完整密钥。

## 6. 首期交付顺序

1. 支付领域模型、币种隔离、统一订单状态机和账务幂等。
2. 可插拔渠道接口、沙箱/模拟渠道、收银台页面和回调测试工具。
3. 支付宝、微信官方接口适配。
4. 易支付适配。
5. EPUSDT、GMPAY 的收银台及 API 模式，按商户正式文档接入并完成真实测试。
6. 对账、异常订单、告警和审计。

## 7. 验收要求

- CNY 与 USDT 不存在汇率字段、汇率服务或换算代码路径。
- 不匹配币种的渠道不能创建支付单。
- 支付成功通知重放 100 次只能入账一次。
- 篡改金额、币种、订单号或签名必须拒绝。
- 浏览器伪造成功返回不得导致余额增加。
- 收银台关闭、过期、重复打开和支付中状态显示正确。
- 商户密钥不出现在前端 bundle、浏览器响应或日志中。
- EPUSDT/GMPAY 只有在正式商户接口联调通过后，才标记为真实渠道已接通。
## 8. EPUSDT / GMPay 官方协议适配补充

本项目按当前公开商户文档实现，不使用已经废弃的旧路由：

- EPUSDT GMPay 创建订单：`POST /payments/gmpay/v1/order/create-transaction`。
- EPUSDT 收银台：创建响应中的 `data.payment_url`；浏览器入口为 `/pay/checkout-counter/{trade_id}`。
- EPUSDT 收银台配置：`GET /payments/gmpay/v1/config`；读取收银台详情：`GET /pay/checkout-counter-resp/{trade_id}`；状态查询：`GET /pay/check-status/{trade_id}`。
- EPUSDT GMPay 签名：排除 `signature` 与空值，按 ASCII 字段名排序，拼接 `key=value`，使用商户 Secret 计算小写 HMAC-SHA256。
- GMPay Edge 创建订单：`POST /payments/gmpay/v1/order/create-transaction`；查单：`GET /payments/gmpay/v1/order/query`，按文档要求对查询字段签名。
- GMPay Edge 创建成功响应包含 `data.payment_url`，商户跳转到该托管收银台；回调为 JSON POST，验签后应答 HTTP 200 和纯文本 `ok`。
- GMPay 回调使用与下单相同的 HMAC-SHA256 规范化规则；必须校验 PID、签名、平台订单号、金额和支付状态，并通过幂等流水入账。
- EPay 兼容模式与 GMPay 主协议不同：使用 `sign` 字段，排除 `sign`、`sign_type`，其余非空参数按 ASCII 顺序拼接后追加 Secret 并计算 MD5。必须单独适配，禁止与 GMPay HMAC-SHA256 混用。
- EPUSDT 的 EPay 兼容路由为 `/payments/epay/v1/order/create-transaction/submit.php`；具体实例可能返回 302 收银台跳转。GMPay Edge OpenAPI 也提供 EPay MAPI JSON 响应和独立查单路由。适配器必须根据实际部署产品/版本选择对应模式，不能假设所有部署响应完全相同。

### 币种边界（重要）

EPUSDT/GMPay 的公开接口将 `currency` 定义为法币计价币种（如 CNY/USD），将 `token` 定义为实际链上资产（如 USDT），并可能返回 `actual_amount`。因此：
- Auth 平台自己的 CNY、USDT 余额和订单永远独立，不做汇率换算。
- 适配器不得自行把平台 USDT 订单换算成 CNY/USD，也不得自动把 CNY 映射为 USDT。
- 供应商接口要求的 `provider_currency` 与 `provider_amount` 必须作为独立显式配置/订单快照传入；没有明确的商户计价约定时拒绝创建订单。
- 如果供应商只能接受法币金额并按汇率计算链上 USDT 数量，该模式必须作为供应商侧计价展示，不得回写或改变 Auth 平台账务币种；若需求要求精确固定 USDT 金额，则必须先确认供应商提供固定 token 金额的正式接口能力，否则该模式不得启用。

官方资料：
- [EPUSDT 商户支付 API](https://epusdt.com/zh/api/payment)
- [EPUSDT API 路由参考](https://epusdt.com/zh/api/reference)
- [GMPay Edge 商户 API](https://www.epusdt.com/gmpay-edge/merchant-api)
- [GMPay Edge OpenAPI](https://github.com/GMWalletApp/gmpay-edge/blob/main/public/openapi.yaml)
