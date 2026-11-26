import { useEffect, useMemo, useState, type FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { AlertCircle, ArrowRight, LoaderCircle, ShieldCheck } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

type Currency = "CNY" | "USDT";
type Channel = {
  channel_code: string;
  display_name: string;
  allowed_currencies: Currency[];
  cashier_mode: boolean;
};

export function TopUpPage() {
  const navigate = useNavigate();
  const [channels, setChannels] = useState<Channel[]>([]);
  const [currency, setCurrency] = useState<Currency>("CNY");
  const [channelCode, setChannelCode] = useState("");
  const [amount, setAmount] = useState("");
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    fetch("/api/payments/channels", {
      credentials: "include",
      headers: { Accept: "application/json" },
    })
      .then(async (response) => {
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || "读取可用支付渠道失败");
        const items = result.data as Channel[];
        setChannels(items);
        const first = items.find((item) => item.allowed_currencies.includes("CNY"));
        if (first) setChannelCode(first.channel_code);
      })
      .catch((cause) => setError(cause instanceof Error ? cause.message : "读取支付渠道失败"))
      .finally(() => setLoading(false));
  }, []);

  const availableChannels = useMemo(
    () => channels.filter((channel) => channel.allowed_currencies.includes(currency)),
    [channels, currency],
  );

  useEffect(() => {
    if (!availableChannels.some((channel) => channel.channel_code === channelCode)) {
      setChannelCode(availableChannels[0]?.channel_code || "");
    }
  }, [availableChannels, channelCode]);

  const currencySymbol = currency === "CNY" ? "¥" : "U";
  const amountStep = currency === "CNY" ? "0.01" : "0.00000001";

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");

    const numericAmount = Number(amount);
    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
      setError("请输入大于 0 的充值金额");
      return;
    }
    if (currency === "CNY" && !/^\d+(?:\.\d{1,2})?$/.test(amount)) {
      setError("人民币金额最多保留两位小数");
      return;
    }
    if (currency === "USDT" && !/^\d+(?:\.\d{1,8})?$/.test(amount)) {
      setError("USDT 金额最多保留八位小数");
      return;
    }
    if (!channelCode) {
      setError("当前币种没有可用支付渠道");
      return;
    }

    setSubmitting(true);
    try {
      const response = await fetch("/api/payments/orders", {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "Idempotency-Key": crypto.randomUUID().replaceAll("-", ""),
        },
        body: JSON.stringify({
          channel_code: channelCode,
          currency,
          amount,
          subject: "Account top-up",
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || "创建支付订单失败");
      }
      navigate(result.data.checkout_url);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "创建支付订单失败");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium text-primary">账户中心 / 在线充值</p>
        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">账户充值</h1>
        <p className="max-w-3xl text-sm leading-6 text-muted-foreground">
          选择结算币种、充值金额和支付渠道。CNY 与 USDT 分别记账，平台不会进行汇率换算或币种互兑。
        </p>
      </div>

      <form onSubmit={submit} className="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div className="flex flex-col gap-5">
          <Card>
            <CardHeader className="gap-1">
              <CardTitle className="text-base">1. 选择结算币种</CardTitle>
              <CardDescription>两种币种独立余额、独立订单。</CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Button type="button" size="lg" variant={currency === "CNY" ? "default" : "outline"} className="h-14 justify-start gap-3" onClick={() => setCurrency("CNY")}>
                <span className="text-xl">¥</span>
                <span className="flex flex-col items-start gap-0.5">
                  <span>人民币 CNY</span>
                  <span className="text-xs opacity-75">独立人民币账户</span>
                </span>
              </Button>
              <Button type="button" size="lg" variant={currency === "USDT" ? "default" : "outline"} className="h-14 justify-start gap-3" onClick={() => setCurrency("USDT")}>
                <span className="text-xl">U</span>
                <span className="flex flex-col items-start gap-0.5">
                  <span>USDT</span>
                  <span className="text-xs opacity-75">独立 U 账户</span>
                </span>
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="gap-1">
              <CardTitle className="text-base">2. 选择支付渠道</CardTitle>
              <CardDescription>只显示已启用且支持当前币种的渠道。</CardDescription>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="flex items-center gap-3 py-5 text-sm text-muted-foreground">
                  <LoaderCircle className="size-4 animate-spin" />
                  正在读取支付渠道…
                </div>
              ) : availableChannels.length === 0 ? (
                <p className="rounded-lg border border-dashed p-5 text-sm text-muted-foreground">当前币种暂时没有可用支付渠道，请联系管理员。</p>
              ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  {availableChannels.map((channel) => (
                    <button
                      key={channel.channel_code}
                      type="button"
                      onClick={() => setChannelCode(channel.channel_code)}
                      className={"flex min-h-20 items-center justify-between gap-3 rounded-lg border p-4 text-left transition-colors " + (channelCode === channel.channel_code ? "border-primary bg-primary/5 ring-1 ring-primary" : "hover:bg-muted/60")}
                    >
                      <span className="flex flex-col gap-1">
                        <span className="font-medium">{channel.display_name}</span>
                        <span className="text-xs text-muted-foreground">{channel.channel_code}</span>
                      </span>
                      <Badge variant={channelCode === channel.channel_code ? "default" : "outline"}>托管收银台</Badge>
                    </button>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="gap-1">
              <CardTitle className="text-base">3. 输入充值金额</CardTitle>
              <CardDescription>{currency === "CNY" ? "人民币最多两位小数。" : "USDT 最多八位小数。"}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
              <label htmlFor="topup-amount" className="text-sm font-medium">充值金额</label>
              <div className="relative">
                <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{currencySymbol}</span>
                <Input
                  id="topup-amount"
                  type="number"
                  inputMode="decimal"
                  min="0"
                  step={amountStep}
                  value={amount}
                  onChange={(event) => setAmount(event.target.value)}
                  className="h-12 pl-9 text-lg tabular-nums"
                  placeholder={currency === "CNY" ? "请输入人民币金额" : "请输入 USDT 数量"}
                  required
                  disabled={submitting}
                />
              </div>
            </CardContent>
          </Card>
        </div>

        <aside className="flex flex-col gap-5">
          <Card className="xl:sticky xl:top-24">
            <CardHeader className="gap-1">
              <CardTitle className="text-base">充值确认</CardTitle>
              <CardDescription>请核对币种、金额和渠道。</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
              <div className="flex flex-col gap-2 rounded-xl bg-muted/60 p-5">
                <p className="text-sm text-muted-foreground">本次应付</p>
                <p className="text-3xl font-semibold tabular-nums">{currencySymbol}{amount || "0.00"}{currency === "USDT" ? " U" : ""}</p>
                <Badge variant="outline" className="w-fit">{currency}</Badge>
              </div>
              <div className="flex flex-col gap-2 text-sm">
                <div className="flex items-center justify-between gap-3">
                  <span className="text-muted-foreground">支付渠道</span>
                  <span className="font-medium">{availableChannels.find((item) => item.channel_code === channelCode)?.display_name || "未选择"}</span>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <span className="text-muted-foreground">币种换算</span>
                  <span className="font-medium">不进行换算</span>
                </div>
              </div>

              {error ? (
                <div role="alert" className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                  <AlertCircle className="mt-0.5 size-4 shrink-0" />
                  <p>{error}</p>
                </div>
              ) : null}

              <Button type="submit" className="h-11 w-full" disabled={loading || submitting || !channelCode || !amount}>
                {submitting ? <LoaderCircle className="size-4 animate-spin" /> : <ArrowRight className="size-4" />}
                {submitting ? "正在创建订单…" : "创建支付订单"}
              </Button>
              <p className="flex items-start gap-2 text-xs leading-5 text-muted-foreground">
                <ShieldCheck className="mt-0.5 size-3.5 shrink-0" />
                支付成功以服务端验签回调为准。请勿重复付款；关闭页面不会取消订单。
              </p>
            </CardContent>
          </Card>
        </aside>
      </form>
    </section>
  );
}
