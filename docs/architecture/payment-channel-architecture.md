# 支付渠道与收银台架构

## 1. 平台结算币种

平台支持 CNY 与 USDT 两种独立记账币种：
- CNY 展示为人民币，例如 ¥100.00。
- USDT 展示为 U，例如 100 U。
- 两种币种独立余额、独立订单、独立价格配置、独立流水；禁止汇率换算、自动兑换或跨币种抵扣。
- 订单创建时固化平台币种和应付金额。渠道必须显式声明可承接的产品/币种配置；不匹配时拒绝创建支付单。
- 账务使用精确十进制或币种最小单位整数，禁止浮点计算。

## 2. 渠道范围

首期渠道：
1. 支付宝官方支付接口（CNY）。
2. 微信支付官方支付接口（CNY）。
3. 易支付（按商户实际开通的产品和协议配置）。
4. EPUSDT。
5. GMPay。

每个渠道独立适配：下单、收银台、查单、回调验签、状态映射、错误处理不得混用签名算法或字段协议。

## 3. EPUSDT / GMPay 协议

当前 EPUSDT 商户 API 与 GMPay Edge 公开协议的 GMPay 主流程均使用以下接口形式：

- 创建订单：POST `/payments/gmpay/v1/order/create-transaction`，支持 JSON 或 `application/x-www-form-urlencoded`。
- 签名：排除 `signature`、空字符串和 null；按字段名 ASCII 顺序排序；拼接 `key=value` 并以 `&` 连接；使用商户 Secret 计算小写 HMAC-SHA256。
- 必须包含 PID、平台订单号、供应商计价币种、金额和 HTTPS notify_url。
- `token` 与 `network` 必须同时提供或同时省略。省略时由托管收银台让付款人选择，不得私自默认链。
- 创建响应的 `data.payment_url` 是浏览器收银台地址；不得将它当作普通页面地址自行拼接。
- EPUSDT 状态查询：GET `/pay/check-status/{trade_id}`；查询订单详情：GET `/pay/checkout-counter-resp/{trade_id}`。
- GMPay Edge 查单：GET `/payments/gmpay/v1/order/query`，提供且仅提供 `trade_id` 或 `order_id` 其中一个，连同 PID 按 GMPay HMAC 规则签名。
- GMPay 回调为 JSON POST，必须校验 PID、HMAC 签名、平台订单号、供应商金额和成功状态；成功状态为 `status=2`。确认数据库事务提交后返回 HTTP 200、纯文本 `ok`。
- EPUSDT 状态含义：1 等待支付、2 支付成功、3 过期、4 等待选择支付方式、5 链上交易确认中、6 进入人工复核。只有状态 2 可自动入账；状态 5 不能提前入账。

## 4. EPay 兼容协议与收银台

EPay 是独立兼容协议，不得与 GMPay HMAC 签名混用：

- 创建订单：POST `/payments/epay/v1/order/create-transaction/mapi.php`（也可按部署文档使用 `submit.php`）。
- MAPI 返回 JSON，包含 `trade_no` 和 `payurl`；平台将 `payurl` 作为收银台 URL 返回前端，由浏览器跳转。不能把 JSON MAPI 误认为 HTTP 302。
- EPay 签名排除 `sign` 和 `sign_type`，其余非空参数按 ASCII 顺序拼接后追加商户 Secret，计算小写 MD5。
- 查单：GET `/payments/epay/v1/order/create-transaction/api.php`，使用 `act=order`、PID 和 `trade_no`（或协议允许的订单号）签名查询。
- EPay 回调使用 GET 参数，必须校验 PID、MD5 签名、`out_trade_no`、金额和 `trade_status=TRADE_SUCCESS`。
- 只有 EPay 的明确成功状态可以入账；浏览器返回页不是支付成功凭据。

## 5. 币种边界

EPUSDT/GMPay 协议中的 `currency` 是供应商侧法币计价单位（例如 CNY/USD），`token` 是实际接收资产（例如 USDT），`actual_amount` 是供应商计算的链上资产数量。这些字段不能被误认为 Auth 平台的币种转换功能。

- Auth 的 CNY、USDT 余额与订单独立，不做汇率换算。
- 供应商侧 `provider_currency`、`provider_amount`、token/network 必须作为下单快照保存，并按商户正式配置显式传入。
- 不得读取 `actual_amount` 作为 Auth 入账金额。
- 不得自动将平台 USDT 转为 CNY/USD，或将平台 CNY 自动换算成 USDT。
- 若某渠道配置无法在不发生平台换算的前提下承接该平台订单币种，必须拒绝创建订单；不能静默换算或改变平台账务币种。

## 6. 统一支付生命周期与幂等

`CREATED -> PENDING -> PAID | FAILED | CLOSED | EXPIRED | REVIEW`

- 创建订单时生成平台唯一订单号，并保存平台币种/金额和供应商币种/金额快照。
- 收银台 URL 必须来自渠道响应，且通过 HTTPS、域名白名单校验；禁止开放重定向。
- 浏览器同步跳转仅用于展示结果，不作为到账依据。
- 只有服务端验签通过的异步通知或可信查单结果，才能将订单置为 PAID。
- 回调处理、钱包余额增加、账务流水写入和订单状态更新必须在同一数据库事务内。
- 订单行锁 + 唯一幂等流水键确保重复回调不会重复入账。
- 回调必须验证商户身份、签名、订单号、供应商金额、币种（渠道回调提供时）及成功状态。
- 对账任务定期主动查单；金额或币种不一致进入人工异常队列，不得自动入账。
- 回调原始数据只保存必要摘要/哈希，密钥、签名材料不得写入普通日志。

## 7. 商户凭据与配置

- 每个渠道独立配置启用状态、PID/商户号、加密凭据、API 地址、回调地址、同步返回地址、收银台域名白名单、订单超时、查单策略及可用币种。
- 商户 Secret 使用 AES-256-GCM 加密保存；主密钥仅从部署环境 `APP_KEY` 读取，至少 32 字符，不得提交到仓库。
- 前端只显示脱敏凭据；服务端回调处理时解密，不向浏览器返回完整密钥。
- 回调地址必须为 HTTPS。
- 商户配置必须匹配实际产品版本和 OpenAPI，不得猜测接口字段。

## 8. 后续渠道交付

1. 支付宝官方接口与微信支付官方接口。
2. 易支付独立适配器与商户协议测试。
3. EPUSDT/GMPay 商户沙箱和真实订单联调。
4. 收银台页面、订单状态轮询和异常状态处理。
5. 主动查单、对账、回调失败重试和审计后台。

官方协议：
- [EPUSDT 商户支付 API](https://www.epusdt.com/zh/api/payment)
- [EPUSDT API 路由参考](https://www.epusdt.com/zh/api/reference)
- [GMPay Edge 商户 API](https://www.epusdt.com/gmpay-edge/merchant-api)
- [GMPay Edge OpenAPI](https://github.com/GMWalletApp/gmpay-edge/blob/main/public/openapi.yaml)