import { ArrowUpRight, CreditCard, Wallet } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const channels = [
  { name: "支付宝官方接口", code: "ALIPAY", currency: "CNY", mode: "官方 API", status: "待接入" },
  { name: "微信支付官方接口", code: "WECHAT_PAY", currency: "CNY", mode: "官方 API", status: "待接入" },
  { name: "易支付", code: "EPAY", currency: "按商户配置", mode: "API / 收银台", status: "待接入" },
  { name: "EPUSDT", code: "EPUSDT", currency: "USDT", mode: "收银台 + API（按商户文档）", status: "待商户联调" },
  { name: "GMPAY", code: "GMPAY", currency: "按商户配置", mode: "收银台 + API（按商户文档）", status: "待商户联调" },
];

export function PaymentsPage() {
  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium text-primary">财务中心 / 支付配置</p>
        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">支付渠道</h1>
        <p className="max-w-3xl text-sm leading-6 text-muted-foreground">
          管理独立支付渠道和收银台接入。CNY 与 USDT 分别记账，不进行汇率换算或跨币种抵扣。
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardHeader className="flex-row items-center justify-between gap-4">
            <div className="flex flex-col gap-1.5">
              <CardTitle className="text-base">人民币账户</CardTitle>
              <CardDescription>独立 CNY 余额与订单</CardDescription>
            </div>
            <Wallet className="size-5 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">¥ CNY</p>
            <p className="mt-2 text-xs text-muted-foreground">仅使用人民币支付渠道结算</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex-row items-center justify-between gap-4">
            <div className="flex flex-col gap-1.5">
              <CardTitle className="text-base">USDT 账户</CardTitle>
              <CardDescription>独立 USDT 余额与订单</CardDescription>
            </div>
            <CreditCard className="size-5 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">U · USDT</p>
            <p className="mt-2 text-xs text-muted-foreground">仅使用已开通的 USDT 支付渠道结算</p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>已规划支付渠道</CardTitle>
          <CardDescription>实际启用状态、商户参数和支付方式将在接入真实服务后读取。</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col gap-3">
            {channels.map((channel) => (
              <div key={channel.code} className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 flex-col gap-1.5">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="font-medium">{channel.name}</p>
                    <Badge variant="outline">{channel.currency}</Badge>
                  </div>
                  <p className="text-sm text-muted-foreground">{channel.mode}</p>
                </div>
                <div className="flex items-center justify-between gap-3 sm:justify-end">
                  <Badge variant="warning">{channel.status}</Badge>
                  <ArrowUpRight className="size-4 text-muted-foreground" />
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
      <p className="text-xs leading-5 text-muted-foreground">
        当前为前端配置展示骨架，不会伪造渠道已接通状态。真实接入需配置商户资料并完成签名、回调、查单及收银台联调。
      </p>
    </section>
  );
}