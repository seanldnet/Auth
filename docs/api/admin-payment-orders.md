# 管理员全站充值订单查询

GET /api/admin/payment-orders（管理员会话鉴权）

Query:
- page：页码，默认 1。
- page_size：每页数量，最大 100。
- status：CREATING / PENDING / PAID / EXPIRED / CANCELLED / REVIEW。
- keyword：订单号、用户名或支付渠道交易号关键字。

返回用户、订单金额、支付币种、渠道、渠道金额快照、渠道交易号、订单状态、创建/过期/支付时间。该接口只读，不提供直接改为 PAID 的操作；支付入账必须经过已验签的支付回调或独立的受审计人工核账流程。
