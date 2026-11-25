import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { AlertCircle, CheckCircle2, Clock3, ExternalLink, LoaderCircle, ShieldCheck } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

type CashierOrder = {
  order_no: string;
  channel_code: string;
  currency: "CNY" | "USDT";
  amount: string;
  display_amount: string;
  status: "CREATING" | "PENDING" | "PAID" | "EXPIRED" | "CANCELLED" | "REVIEW";
  provider_cashier_url: string | null;
  expires_at: string | null;
  paid_at: string | null;
};

const statusMeta: Record<CashierOrder["status"], { label: string; variant: "secondary" | "outline" | "success" | "warning" }> = {
  CREATING: { label: "正在创建支付订单", variant: "secondary" },
  PENDING: { label: "等待支付", variant: "warning" },
  PAID: { label: "支付成功", variant: "success" },
  EXPIRED: { label: "订单已过期", variant: "warning" },
  CANCELLED: { label: "订单已取消", variant: "outline" },
  REVIEW: { label: "支付结果待核查", variant: "outline" },
};

export function CheckoutPage() {
  const { orderNo = "", token = "" } = useParams();
  const [order, setOrder] = useState<CashierOrder | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    let timer: ReturnType<typeof setInterval> | undefined;

    const load = async () => {
      try {
        const response = await fetch(
          "/api/payments/cashier/" + encodeURIComponent(orderNo) + "/" + encodeURIComponent(token),
          { headers: { Accept: "application/json" }, cache: "no-store" },
        );
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || "无法读取支付订单");
        }
        if (!active) return;

        const nextOrder = result.data as CashierOrder;
        setOrder(nextOrder);
        setError("");
        setLoading(false);

        if (nextOrder.status !== "PENDING" && nextOrder.status !== "CREATING" && timer) {
          clearInterval(timer);
          timer = undefined;
        }
      } catch (cause) {
        if (!active) return;
        setError(cause instanceof Error ? cause.message : "读取支付订单失败");
        setLoading(false);
      }
    };

    void load();
    timer = setInterval(() => void load(), 5000);

    return () => {
      active = false;
      if (timer) clearInterval(timer);
    };
  }, [orderNo, token]);

  if (loading) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
        <Card className="w-full max-w-lg">
          <CardContent className="flex items-center justify-center gap-3 p-8 text-sm text-muted-foreground">
            <LoaderCircle className="size-5 animate-spin" />
            正在加载收银台订单…
          </CardContent>
        </Card>
      </main>
    );
  }

  if (error || !order) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
        <Card className="w-full max-w-lg">
          <CardHeader className="gap-3">
            <AlertCircle className="size-8 text-destructive" />
            <CardTitle>无法打开收银台</CardTitle>
            <CardDescription>{error || "订单不存在或访问凭据无效，请从平台订单页面重新进入。"}</CardDescription>
          </CardHeader>
        </Card>
      </main>
    );
  }

  const status = statusMeta[order.status];
  const paid = order.status === "PAID";
  const pending = order.status === "PENDING";

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4 sm:p-6">
      <Card className="w-full max-w-xl shadow-sm">
        <CardHeader className="gap-3 border-b pb-5">
          <div className="flex items-center gap-2 text-sm font-medium text-primary">
            <ShieldCheck className="size-4" />
            Auth 安全收银台
          </div>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-col gap-1">
              <CardTitle className="text-xl">订单支付</CardTitle>
              <CardDescription>支付结果由服务端异步通知确认，请勿仅凭浏览器跳转判断到账。</CardDescription>
            </div>
            <Badge variant={status.variant}>{status.label}</Badge>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6 p-5 sm:p-7">
          <section className="flex flex-col items-center gap-2 rounded-xl bg-muted/60 px-4 py-6 text-center">
            <p className="text-sm text-muted-foreground">应付金额</p>
            <p className="text-4xl font-semibold tracking-tight tabular-nums">{order.display_amount}</p>
            <p className="text-xs text-muted-foreground">平台币种：{order.currency}</p>
          </section>

          <dl className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div className="flex flex-col gap-1">
              <dt className="text-muted-foreground">平台订单号</dt>
              <dd className="break-all font-medium">{order.order_no}</dd>
            </div>
            <div className="flex flex-col gap-1">
              <dt className="text-muted-foreground">支付渠道</dt>
              <dd className="font-medium">{order.channel_code}</dd>
            </div>
            <div className="flex flex-col gap-1">
              <dt className="text-muted-foreground">订单有效期</dt>
              <dd className="font-medium">{order.expires_at || "以渠道订单为准"}</dd>
            </div>
            <div className="flex flex-col gap-1">
              <dt className="text-muted-foreground">订单状态</dt>
              <dd className="font-medium">{status.label}</dd>
            </div>
          </dl>

          {paid ? (
            <div className="flex items-center gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-4 text-sm">
              <CheckCircle2 className="size-5 shrink-0 text-emerald-600" />
              <div className="flex flex-col gap-1">
                <p className="font-medium">支付已确认</p>
                <p className="text-muted-foreground">余额已由服务端支付回调流程处理。</p>
              </div>
            </div>
          ) : null}

          {pending && order.provider_cashier_url ? (
            <Button asChild className="h-11 w-full">
              <a href={order.provider_cashier_url} target="_blank" rel="noreferrer">
                前往支付渠道收银台
                <ExternalLink className="ml-2 size-4" />
              </a>
            </Button>
          ) : null}

          {order.status === "CREATING" ? (
            <div className="flex items-center gap-3 rounded-lg border p-4 text-sm text-muted-foreground">
              <LoaderCircle className="size-5 animate-spin" />
              正在向支付渠道创建订单，请稍候…
            </div>
          ) : null}

          {order.status === "REVIEW" ? (
            <div className="flex items-center gap-3 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
              <AlertCircle className="size-5 shrink-0 text-amber-600" />
              <p>渠道订单创建结果需要人工核查。请勿重复付款，平台确认后会更新订单状态。</p>
            </div>
          ) : null}

          {pending ? (
            <p className="flex items-center justify-center gap-2 text-xs text-muted-foreground">
              <Clock3 className="size-3.5" />
              页面每 5 秒自动刷新支付状态；关闭页面不会取消订单。
            </p>
          ) : null}
        </CardContent>
      </Card>
    </main>
  );
}
