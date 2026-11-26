import { useCallback, useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Clock3, ExternalLink, History, LoaderCircle, RefreshCw } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

type PaymentOrder = {
  order_no: string;
  channel_code: string;
  currency: "CNY" | "USDT";
  amount: string;
  status: "CREATING" | "PENDING" | "PAID" | "EXPIRED" | "CANCELLED" | "REVIEW";
  created_at: string;
  expires_at: string | null;
  paid_at: string | null;
  checkout_url?: string | null;
  username?: string;
  display_amount?: string;
};

const statusMeta: Record<PaymentOrder["status"], { label: string; variant: "secondary" | "outline" | "success" | "warning" }> = {
  CREATING: { label: "创建中", variant: "secondary" },
  PENDING: { label: "待支付", variant: "warning" },
  PAID: { label: "已支付", variant: "success" },
  EXPIRED: { label: "已过期", variant: "outline" },
  CANCELLED: { label: "已取消", variant: "outline" },
  REVIEW: { label: "待人工核查", variant: "warning" },
};

export function PaymentOrdersPage() {
  const navigate = useNavigate();
  const [items, setItems] = useState<PaymentOrder[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [role, setRole] = useState("USER");
  const [page, setPage] = useState(1);
  const [keyword, setKeyword] = useState("");
  const [statusFilter, setStatusFilter] = useState("");

  useEffect(() => { fetch("/api/auth/me", { credentials: "include", headers: { Accept: "application/json" } }).then(r=>r.json()).then(j=>{ if(j.success) setRole(j.data.user.role); }).catch(()=>undefined); }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const endpoint = role === "ADMIN" ? "/api/admin/payment-orders" : "/api/payments/orders";
      const params = new URLSearchParams({ page: String(page), page_size: "20", keyword: role === "ADMIN" ? keyword : "", status: role === "ADMIN" ? statusFilter : "" });
      const response = await fetch(endpoint + "?" + params, {
        credentials: "include",
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || "读取充值记录失败");
      }
      setItems(result.data.items as PaymentOrder[]);
      setTotal(Number(result.data.total || 0));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "读取充值记录失败");
    } finally {
      setLoading(false);
    }
  }, [role, page, keyword, statusFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex flex-col gap-2">
          <p className="text-sm font-medium text-primary">{role === "ADMIN" ? "财务中心 / 全站订单" : "账户中心 / 充值记录"}</p>
          <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{role === "ADMIN" ? "全站充值订单" : "充值订单"}</h1>
          <p className="text-sm text-muted-foreground">{role === "ADMIN" ? "管理员查看全站订单、支付渠道、用户和支付状态。" : "查看订单状态；未支付订单可以继续打开安全收银台。"}</p>
        </div>
        <Button variant="outline" onClick={() => void load()} disabled={loading}>
          <RefreshCw className={"size-4 " + (loading ? "animate-spin" : "")} />
          刷新记录
        </Button>
      </div>

      <Card>
        <CardHeader className="flex-row items-center justify-between gap-3">
          <div className="flex flex-col gap-1">
            <CardTitle className="text-base">订单列表</CardTitle>
            <CardDescription>最近 20 笔订单 · 共 {total} 笔</CardDescription>
          </div>
          <History className="size-5 text-muted-foreground" />
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          {role === "ADMIN" ? <div className="grid gap-3 sm:grid-cols-[1fr_200px]"><input className="h-10 rounded-md border bg-background px-3 text-sm" value={keyword} onChange={e=>{setKeyword(e.target.value);setPage(1);}} placeholder="搜索订单号 / 用户 / 交易号"/><select className="h-10 rounded-md border bg-background px-3 text-sm" value={statusFilter} onChange={e=>{setStatusFilter(e.target.value);setPage(1);}}><option value="">全部状态</option>{Object.entries(statusMeta).map(([k,v])=><option key={k} value={k}>{v.label}</option>)}</select></div> : null}
          {error ? <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{error}</p> : null}
          {loading ? (
            <div className="flex items-center justify-center gap-3 py-8 text-sm text-muted-foreground">
              <LoaderCircle className="size-5 animate-spin" />
              正在读取订单…
            </div>
          ) : items.length === 0 ? (
            <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed p-8 text-center">
              <History className="size-8 text-muted-foreground" />
              <p className="font-medium">还没有充值订单</p>
              <p className="text-sm text-muted-foreground">创建充值订单后，会在这里显示支付状态。</p>
              <Button onClick={() => navigate("/top-up")}>立即充值</Button>
            </div>
          ) : (
            items.map((order) => {
              const status = statusMeta[order.status];
              return (
                <div key={order.order_no} className="flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex min-w-0 flex-col gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-semibold tabular-nums">{order.display_amount || (order.currency === "CNY" ? "¥" + order.amount : order.amount + " USDT")}</span>
                      <Badge variant={status.variant}>{status.label}</Badge>
                      <Badge variant="outline">{order.currency}</Badge>
                    </div>
                    <p className="break-all text-xs text-muted-foreground">{order.order_no}</p>
                    {role === "ADMIN" && order.username ? <p className="text-xs text-muted-foreground">用户：{order.username}</p> : null}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                      <span>{order.channel_code}</span>
                      <span>创建：{order.created_at}</span>
                      {order.paid_at ? <span>支付：{order.paid_at}</span> : null}
                    </div>
                  </div>
                  {role !== "ADMIN" && order.checkout_url && ["CREATING", "PENDING"].includes(order.status) ? (
                    <Button className="shrink-0" onClick={() => navigate(order.checkout_url!)}>
                      继续支付
                      <ExternalLink className="ml-2 size-4" />
                    </Button>
                  ) : order.status === "REVIEW" ? (
                    <span className="flex items-center gap-2 text-sm text-muted-foreground">
                      <Clock3 className="size-4" />
                      请等待平台核查
                    </span>
                  ) : null}
                </div>
              );
            })
          )}
        </CardContent>
      </Card>
    </section>
  );
}
