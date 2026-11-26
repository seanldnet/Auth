import { useCallback, useEffect, useState } from "react";
import { Copy, KeyRound, LoaderCircle, Plus, RefreshCw, ShieldAlert } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

type Software = { id: number; name: string; app_id: string; status: string };
type Plan = { id: number; name: string; plan_code: string; duration_unit: string; duration_value: number | null; price: string; currency: string; status: string };
type License = { id: number; license_no: string; license_key_hint: string; plan_id: number; plan_name: string; status: string; device_label: string | null; activated_at: string | null; expires_at: string | null; machine_change_count: number; created_at: string };
type GeneratedLicense = { id: number; license_no: string; license_key: string };

async function request<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, { credentials: "include", headers: { Accept: "application/json", "Content-Type": "application/json" }, ...init });
  const result = await response.json();
  if (!response.ok || !result.success) throw new Error(result.message || "请求失败");
  return result.data as T;
}
const statusText: Record<string, string> = { GENERATED: "待激活", ACTIVE: "正常", FROZEN: "已冻结", VOID: "已作废", EXPIRED: "已过期" };

export function LicensesPage() {
  const [software, setSoftware] = useState<Software[]>([]);
  const [selectedSoftwareId, setSelectedSoftwareId] = useState<number | null>(null);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);
  const [licenses, setLicenses] = useState<License[]>([]);
  const [total, setTotal] = useState(0);
  const [quantity, setQuantity] = useState("10");
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState("");
  const [keyword, setKeyword] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [generated, setGenerated] = useState<GeneratedLicense[]>([]);
  const [importText, setImportText] = useState("");

  const loadSoftware = useCallback(async () => {
    try {
      const rows = await request<Software[]>("/api/admin/software");
      const active = rows.filter((item) => item.status === "ACTIVE");
      setSoftware(active);
      if (!selectedSoftwareId || !active.some((item) => item.id === selectedSoftwareId)) setSelectedSoftwareId(active[0]?.id ?? null);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "读取软件失败"); }
  }, [selectedSoftwareId]);

  const loadPlans = useCallback(async (id: number) => {
    try {
      const rows = await request<Plan[]>(`/api/admin/software/${id}/plans`);
      const active = rows.filter((item) => item.status === "ACTIVE");
      setPlans(active);
      if (!active.some((item) => item.id === selectedPlanId)) setSelectedPlanId(active[0]?.id ?? null);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "读取套餐失败"); }
  }, [selectedPlanId]);

  const loadLicenses = useCallback(async () => {
    if (!selectedSoftwareId) { setLicenses([]); setTotal(0); setLoading(false); return; }
    setLoading(true); setError("");
    try {
      const params = new URLSearchParams({ page: String(page), page_size: "20", status: statusFilter, keyword });
      const result = await request<{ items: License[]; total: number }>(`/api/admin/software/${selectedSoftwareId}/licenses?${params}`);
      setLicenses(result.items); setTotal(result.total);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "读取授权列表失败"); }
    finally { setLoading(false); }
  }, [selectedSoftwareId, page, statusFilter, keyword]);

  useEffect(() => { void loadSoftware(); }, [loadSoftware]);
  useEffect(() => { if (selectedSoftwareId) void loadPlans(selectedSoftwareId); else { setPlans([]); setSelectedPlanId(null); } }, [selectedSoftwareId, loadPlans]);
  useEffect(() => { void loadLicenses(); }, [loadLicenses]);

  async function generate(event: React.FormEvent) {
    event.preventDefault();
    if (!selectedSoftwareId || !selectedPlanId) return;
    setSaving(true); setError(""); setNotice(""); setGenerated([]);
    try {
      const result = await request<{ licenses: GeneratedLicense[]; quantity: number }>(`/api/admin/software/${selectedSoftwareId}/licenses`, {
        method: "POST", body: JSON.stringify({ plan_id: selectedPlanId, quantity: Number(quantity) }),
      });
      setGenerated(result.licenses);
      setNotice(`成功生成 ${result.quantity} 个授权码。完整授权码仅本次返回，请立即复制保存。`);
      setPage(1); await loadLicenses();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "生成授权码失败"); }
    finally { setSaving(false); }
  }

  async function importKeys(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selectedSoftwareId || !selectedPlanId) return;
    const keys = importText.split(/[\\r\\n,;，；]+/).map(value => value.trim()).filter(Boolean);
    if (keys.length < 1 || keys.length > 500) { setError("请填写 1–500 个授权码，每行一个。"); return; }
    setSaving(true); setError(""); setNotice("");
    try {
      const result = await request<{ imported: number }>(`/api/admin/software/${selectedSoftwareId}/licenses/import`, {
        method: "POST", body: JSON.stringify({ plan_id: selectedPlanId, license_keys: keys }),
      });
      setNotice(`成功导入 ${result.imported} 个授权码。`);
      setImportText(""); setPage(1); await loadLicenses();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "导入授权码失败"); }
    finally { setSaving(false); }
  }

  async function exportLicenses() {
    if (!selectedSoftwareId) return;
    setError("");
    try {
      const params = new URLSearchParams({ status: statusFilter, keyword });
      const response = await fetch(`/api/admin/software/${selectedSoftwareId}/licenses/export?${params}`, {
        credentials: "include", headers: { Accept: "text/csv" },
      });
      if (!response.ok) {
        const result = await response.json();
        throw new Error(result.message || "导出失败");
      }
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url; anchor.download = "licenses.csv"; anchor.click();
      URL.revokeObjectURL(url);
      setNotice("授权记录 CSV 已导出。出于安全原因，导出文件仅包含授权提示，不包含无法恢复的授权码明文。");
    } catch (cause) { setError(cause instanceof Error ? cause.message : "导出失败"); }
  }

  async function renew(item: License) {
    if (!selectedSoftwareId || !window.confirm(`确认续费授权 ${item.license_no}？`)) return;
    setError(""); setNotice("");
    try {
      const result = await request<{expires_at:string|null;quota_consumed:boolean}>(`/api/admin/licenses/${item.id}/renew`, {
        method:"POST", headers:{"Idempotency-Key":crypto.randomUUID().replaceAll("-","")},
      });
      setNotice(`授权已续费。新到期时间：${result.expires_at||"永久"}。`);
      await loadLicenses();
    } catch (cause) { setError(cause instanceof Error?cause.message:"续费失败"); }
  }

  async function updateStatus(item: License, status: "FROZEN" | "ACTIVE" | "VOID") {
    setError(""); setNotice("");
    try {
      await request<License>(`/api/admin/licenses/${item.id}/status`, { method: "PATCH", body: JSON.stringify({ status }) });
      setNotice(`授权 ${item.license_no} 状态已更新`);
      await loadLicenses();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "更新授权状态失败"); }
  }

  const selectedSoftware = software.find((item) => item.id === selectedSoftwareId);
  const pages = Math.max(1, Math.ceil(total / 20));

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex flex-col gap-2"><p className="text-sm font-medium text-primary">核心业务 / 授权中心</p><h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">授权码管理</h1><p className="max-w-3xl text-sm leading-6 text-muted-foreground">按软件和套餐生成授权码，管理待激活、正常、冻结及作废状态。完整授权码只在生成成功时展示一次，数据库不保存明文。</p></div>
        <div className="flex gap-2"><Button variant="outline" onClick={() => void exportLicenses()} disabled={!selectedSoftwareId}>导出 CSV</Button><Button variant="outline" onClick={() => void loadLicenses()} disabled={loading}><RefreshCw className="size-4" />刷新</Button></div>
      </div>
      {error ? <div role="alert" className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{error}</div> : null}
      {notice ? <div className="rounded-lg border border-emerald-600/20 bg-emerald-600/5 p-3 text-sm text-emerald-700">{notice}</div> : null}
      {generated.length > 0 ? (
        <Card className="border-amber-500/40">
          <CardHeader><CardTitle className="flex items-center gap-2 text-base"><ShieldAlert className="size-4" />新生成的授权码</CardTitle><CardDescription>离开此页面后将无法再次查看完整授权码。请立即复制并安全保存。</CardDescription></CardHeader>
          <CardContent className="flex flex-col gap-3">
            <div className="max-h-72 overflow-auto rounded-md bg-muted p-3"><pre className="whitespace-pre-wrap break-all text-xs leading-6">{generated.map((item) => item.license_key).join("\n")}</pre></div>
            <div className="flex flex-wrap gap-2"><Button size="sm" onClick={() => void navigator.clipboard.writeText(generated.map((item) => item.license_key).join("\n"))}><Copy className="size-4" />复制全部授权码</Button><Button size="sm" variant="outline" onClick={() => void navigator.clipboard.writeText(generated.map((item) => `${item.license_no},${item.license_key}`).join("\n"))}>复制编号和授权码</Button><Button size="sm" variant="ghost" onClick={() => setGenerated([])}>关闭明文展示</Button></div>
          </CardContent>
        </Card>
      ) : null}
      <Card>
        <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Plus className="size-4" />批量生成授权码</CardTitle><CardDescription>单次最多生成 500 个；仅允许选择启用状态的软件和套餐。</CardDescription></CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-2"><label className="text-sm font-medium">选择软件</label><div className="flex flex-wrap gap-2">{software.map((item) => <Button key={item.id} size="sm" variant={selectedSoftwareId === item.id ? "default" : "outline"} onClick={() => { setSelectedSoftwareId(item.id); setPage(1); }}>{item.name}</Button>)}</div>{software.length === 0 ? <p className="text-sm text-muted-foreground">暂无启用的软件，请先到软件管理创建软件。</p> : null}</div>
          <div className="flex flex-col gap-2"><label className="text-sm font-medium">选择套餐</label><div className="flex flex-wrap gap-2">{plans.map((plan) => <Button key={plan.id} size="sm" variant={selectedPlanId === plan.id ? "default" : "outline"} onClick={() => setSelectedPlanId(plan.id)}>{plan.name} · {plan.currency === "CNY" ? "¥" : ""}{plan.price}{plan.currency === "USDT" ? " U" : ""}</Button>)}</div>{selectedSoftwareId && plans.length === 0 ? <p className="text-sm text-muted-foreground">该软件没有启用中的套餐，请先创建并启用套餐。</p> : null}</div>
          <form onSubmit={generate} className="flex flex-col gap-3 sm:flex-row sm:items-end"><div className="flex flex-col gap-2 sm:w-48"><label className="text-sm font-medium">生成数量</label><Input type="number" min={1} max={500} required value={quantity} onChange={(e) => setQuantity(e.target.value)} /></div><Button disabled={saving || !selectedSoftwareId || !selectedPlanId}>{saving ? <LoaderCircle className="size-4 animate-spin" /> : <KeyRound className="size-4" />}{saving ? "正在生成…" : "批量生成授权码"}</Button></form>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle className="text-base">批量导入授权码</CardTitle><CardDescription>每行一个授权码，单次最多 500 个；重复或格式错误时整批回滚。</CardDescription></CardHeader>
        <CardContent><form onSubmit={importKeys} className="flex flex-col gap-3"><label className="text-sm font-medium">授权码列表</label><textarea className="min-h-36 w-full rounded-md border bg-background p-3 font-mono text-xs" value={importText} onChange={e=>setImportText(e.target.value)} placeholder={"每行一个，例如：\\n12345678-12345678-12345678-12345678"} /><div className="flex flex-wrap items-center gap-3"><Button type="submit" disabled={saving||!selectedSoftwareId||!selectedPlanId||!importText.trim()}>{saving?<LoaderCircle className="size-4 animate-spin"/>:<Plus className="size-4"/>}导入授权码</Button><span className="text-xs text-muted-foreground">必须选择软件和套餐；导入成功后仅保存 SHA-256 摘要。</span></div></form></CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle className="text-base">授权记录</CardTitle><CardDescription>{selectedSoftware ? `${selectedSoftware.name} · 共 ${total} 条记录` : "请选择软件"}</CardDescription></CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-3 sm:flex-row"><Input value={keyword} onChange={(e) => { setKeyword(e.target.value); setPage(1); }} placeholder="搜索授权编号或授权码尾号" /><div className="flex flex-wrap gap-2">{["","GENERATED","ACTIVE","FROZEN","VOID","EXPIRED"].map((status) => <Button key={status || "ALL"} size="sm" variant={statusFilter === status ? "default" : "outline"} onClick={() => { setStatusFilter(status); setPage(1); }}>{status ? statusText[status] : "全部"}</Button>)}</div></div>
          {loading ? <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground"><LoaderCircle className="size-4 animate-spin" />正在读取授权记录</div> : licenses.length === 0 ? <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">没有符合条件的授权记录。</div> : <div className="flex flex-col gap-3">{licenses.map((item) => <div key={item.id} className="flex flex-col gap-3 rounded-lg border p-4 lg:flex-row lg:items-center lg:justify-between"><div className="flex min-w-0 flex-col gap-1"><div className="flex flex-wrap items-center gap-2"><span className="font-medium">{item.license_no}</span><Badge variant={item.status === "ACTIVE" ? "success" : item.status === "FROZEN" ? "warning" : "outline"}>{statusText[item.status] || item.status}</Badge></div><span className="text-xs text-muted-foreground">{item.plan_name} · {item.license_key_hint} · {item.created_at}</span><span className="text-xs text-muted-foreground">设备：{item.device_label || "未绑定"} · 换机次数：{item.machine_change_count} · 到期：{item.expires_at || "激活后计算 / 永久"}</span></div><div className="flex flex-wrap gap-2">{item.status === "ACTIVE" || item.status === "EXPIRED" ? <Button size="sm" variant="outline" onClick={() => void renew(item)}>续费</Button> : null}{item.status === "ACTIVE" || item.status === "GENERATED" ? <Button size="sm" variant="outline" onClick={() => void updateStatus(item, "FROZEN")}>冻结</Button> : null}{item.status === "FROZEN" ? <Button size="sm" variant="outline" onClick={() => void updateStatus(item, "ACTIVE")}>恢复</Button> : null}{item.status !== "VOID" && item.status !== "EXPIRED" ? <Button size="sm" variant="destructive" onClick={() => void updateStatus(item, "VOID")}>作废</Button> : null}</div></div>)}</div>}
          <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4 text-sm text-muted-foreground"><span>第 {page} / {pages} 页</span><div className="flex gap-2"><Button size="sm" variant="outline" disabled={page <= 1 || loading} onClick={() => setPage((p) => p - 1)}>上一页</Button><Button size="sm" variant="outline" disabled={page >= pages || loading} onClick={() => setPage((p) => p + 1)}>下一页</Button></div></div>
        </CardContent>
      </Card>
    </section>
  );
}
