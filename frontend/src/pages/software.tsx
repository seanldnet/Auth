import { useCallback, useEffect, useMemo, useState } from "react";
import { AppWindow, Check, Copy, KeyRound, LoaderCircle, PackagePlus, Plus, RefreshCw, ShieldCheck } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

type Software = {
  id: number; app_id: string; name: string; description: string | null;
  status: "ACTIVE" | "DISABLED" | "ARCHIVED"; renew_consumes_quota: number;
  offline_days: number; plan_count: number; created_at: string;
};
type Plan = {
  id: number; software_id: number; name: string; plan_code: string;
  duration_unit: "DAY" | "MONTH" | "YEAR" | "PERMANENT"; duration_value: number | null;
  price: string; currency: "CNY" | "USDT"; status: "ACTIVE" | "DISABLED";
  description: string | null;
};
type PlanForm = {
  name: string; plan_code: string; duration_unit: Plan["duration_unit"];
  duration_value: string; price: string; currency: Plan["currency"];
  status: Plan["status"]; description: string;
};
const emptyPlan: PlanForm = {
  name: "", plan_code: "", duration_unit: "MONTH", duration_value: "1",
  price: "0", currency: "CNY", status: "ACTIVE", description: "",
};

async function api<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, {
    credentials: "include",
    headers: { Accept: "application/json", "Content-Type": "application/json", ...(init?.headers || {}) },
    ...init,
  });
  const result = await response.json();
  if (!response.ok || !result.success) throw new Error(result.message || "请求失败");
  return result.data as T;
}

export function SoftwarePage() {
  const [software, setSoftware] = useState<Software[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [newSecret, setNewSecret] = useState("");
  const [editingSoftware, setEditingSoftware] = useState(false);
  const [softwareForm, setSoftwareForm] = useState({ name: "", description: "", offline_days: "0", renew_consumes_quota: false });
  const [planForm, setPlanForm] = useState<PlanForm>(emptyPlan);
  const [editingPlanId, setEditingPlanId] = useState<number | null>(null);

  const selected = useMemo(() => software.find((item) => item.id === selectedId) || null, [software, selectedId]);

  const loadSoftware = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const rows = await api<Software[]>("/api/admin/software");
      setSoftware(rows);
      if (selectedId && rows.some((item) => item.id === selectedId)) {
        // Keep the current software selection stable across refreshes.
      } else {
        setSelectedId(rows[0]?.id ?? null);
      }
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "读取软件列表失败");
    } finally {
      setLoading(false);
    }
  }, [selectedId]);

  const loadPlans = useCallback(async (id: number) => {
    try {
      setPlans(await api<Plan[]>(`/api/admin/software/${id}/plans`));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "读取套餐失败");
      setPlans([]);
    }
  }, []);

  useEffect(() => { void loadSoftware(); }, [loadSoftware]);
  useEffect(() => {
    if (selectedId) void loadPlans(selectedId);
    else setPlans([]);
  }, [selectedId, loadPlans]);

  useEffect(() => {
    if (selected) {
      setSoftwareForm({
        name: selected.name, description: selected.description || "",
        offline_days: String(selected.offline_days),
        renew_consumes_quota: Boolean(selected.renew_consumes_quota),
      });
      setEditingSoftware(false);
    }
  }, [selected]);

  async function saveSoftware(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true); setError(""); setNotice(""); setNewSecret("");
    try {
      if (editingSoftware && selected) {
        const updated = await api<Software>(`/api/admin/software/${selected.id}`, {
          method: "PUT", body: JSON.stringify(softwareForm),
        });
        setSoftware((rows) => rows.map((row) => row.id === updated.id ? { ...row, ...updated } : row));
        setNotice("软件信息已更新");
        setEditingSoftware(false);
      } else {
        const result = await api<{ software: Software; app_secret: string }>("/api/admin/software", {
          method: "POST", body: JSON.stringify(softwareForm),
        });
        setSoftware((rows) => [result.software, ...rows]);
        setSelectedId(result.software.id);
        setNewSecret(result.app_secret);
        setNotice("软件创建成功。App Secret 仅本次展示，请立即复制并妥善保存。");
        setSoftwareForm({ name: "", description: "", offline_days: "0", renew_consumes_quota: false });
      }
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "保存软件失败");
    } finally { setSaving(false); }
  }

  async function changeStatus(item: Software) {
    const status = item.status === "ACTIVE" ? "DISABLED" : "ACTIVE";
    setError(""); setNotice("");
    try {
      const updated = await api<Software>(`/api/admin/software/${item.id}/status`, {
        method: "PATCH", body: JSON.stringify({ status }),
      });
      setSoftware((rows) => rows.map((row) => row.id === item.id ? { ...row, ...updated } : row));
      setNotice(status === "ACTIVE" ? "软件已启用" : "软件已停用");
    } catch (cause) { setError(cause instanceof Error ? cause.message : "状态更新失败"); }
  }

  function startEditPlan(plan: Plan) {
    setEditingPlanId(plan.id);
    setPlanForm({
      name: plan.name, plan_code: plan.plan_code, duration_unit: plan.duration_unit,
      duration_value: String(plan.duration_value ?? 1), price: plan.price,
      currency: plan.currency, status: plan.status, description: plan.description || "",
    });
  }

  async function savePlan(event: React.FormEvent) {
    event.preventDefault();
    if (!selectedId) return;
    setSaving(true); setError(""); setNotice("");
    try {
      const path = `/api/admin/software/${selectedId}/plans`;
      await api<Plan>(editingPlanId ? `${path}/${editingPlanId}` : path, {
        method: editingPlanId ? "PUT" : "POST",
        body: JSON.stringify({
          ...planForm,
          duration_value: planForm.duration_unit === "PERMANENT" ? null : Number(planForm.duration_value),
        }),
      });
      await loadPlans(selectedId);
      setPlanForm(emptyPlan); setEditingPlanId(null);
      setNotice(editingPlanId ? "套餐已更新" : "套餐已创建");
      await loadSoftware();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "保存套餐失败"); }
    finally { setSaving(false); }
  }

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex flex-col gap-2">
          <p className="text-sm font-medium text-primary">核心业务 / 软件目录</p>
          <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">软件与套餐管理</h1>
          <p className="max-w-3xl text-sm leading-6 text-muted-foreground">管理接入平台的软件身份、授权策略和软件专属套餐。每个套餐严格归属一个软件，价格按 CNY / USDT 独立维护。</p>
        </div>
        <Button variant="outline" onClick={() => void loadSoftware()} disabled={loading}><RefreshCw className="size-4" />刷新列表</Button>
      </div>

      {error ? <div role="alert" className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{error}</div> : null}
      {notice ? <div className="rounded-lg border border-emerald-600/20 bg-emerald-600/5 p-3 text-sm text-emerald-700">{notice}</div> : null}
      {newSecret ? (
        <Card className="border-amber-500/40">
          <CardHeader><CardTitle className="flex items-center gap-2 text-base"><ShieldCheck className="size-4" />软件身份凭据（仅展示一次）</CardTitle><CardDescription>App Secret 不会再次从服务器读取。请复制保存，遗失后需通过密钥轮换流程更换。</CardDescription></CardHeader>
          <CardContent className="flex flex-col gap-3">
            <div className="flex flex-col gap-2"><span className="text-xs text-muted-foreground">App Secret</span><code className="break-all rounded-md bg-muted p-3 text-sm">{newSecret}</code></div>
            <div><Button size="sm" variant="outline" onClick={() => void navigator.clipboard.writeText(newSecret)}><Copy className="size-4" />复制 Secret</Button></div>
          </CardContent>
        </Card>
      ) : null}

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,0.9fr)]">
        <Card>
          <CardHeader className="flex-row items-center justify-between gap-3">
            <div><CardTitle className="flex items-center gap-2 text-base"><AppWindow className="size-4" />软件列表</CardTitle><CardDescription>共 {software.length} 个软件</CardDescription></div>
            <Button size="sm" onClick={() => { setSelectedId(null); setEditingSoftware(false); setSoftwareForm({ name: "", description: "", offline_days: "0", renew_consumes_quota: false }); }}><Plus className="size-4" />新增软件</Button>
          </CardHeader>
          <CardContent className="flex flex-col gap-3">
            {loading ? <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground"><LoaderCircle className="size-4 animate-spin" />正在加载软件</div> : software.length === 0 ? <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">还没有软件，先创建第一个软件。</div> : software.map((item) => (
              <Button key={item.id} type="button" variant="outline" onClick={() => { setSelectedId(item.id); setEditingSoftware(false); }} className={`h-auto w-full flex-col items-stretch gap-2 whitespace-normal rounded-lg p-4 text-left shadow-none hover:bg-muted/50 ${selectedId === item.id ? "border-primary bg-primary/5" : "bg-background"}`}>
                <div className="flex flex-wrap items-center justify-between gap-2"><span className="font-medium">{item.name}</span><Badge variant={item.status === "ACTIVE" ? "success" : "outline"}>{item.status === "ACTIVE" ? "运行中" : item.status === "DISABLED" ? "已停用" : "已归档"}</Badge></div>
                <code className="text-xs text-muted-foreground">{item.app_id}</code>
                <span className="text-xs text-muted-foreground">{item.plan_count} 个套餐 · 离线 {item.offline_days} 天 · 创建于 {item.created_at}</span>
              </button>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2 text-base"><KeyRound className="size-4" />{selected && !editingSoftware ? "软件信息" : editingSoftware ? "编辑软件" : "创建软件"}</CardTitle><CardDescription>App ID 自动生成且不可修改；App Secret 仅创建时展示。</CardDescription></CardHeader>
          <CardContent>
            {selected && !editingSoftware ? (
              <div className="flex flex-col gap-4">
                <div className="rounded-lg bg-muted/50 p-4"><p className="text-xs text-muted-foreground">App ID</p><code className="mt-2 block break-all text-sm">{selected.app_id}</code></div>
                <div><p className="text-sm font-medium">{selected.name}</p><p className="mt-1 text-sm text-muted-foreground">{selected.description || "暂无描述"}</p></div>
                <div className="grid grid-cols-2 gap-3 text-sm"><div className="rounded-lg border p-3"><p className="text-muted-foreground">套餐数量</p><p className="mt-1 text-xl font-semibold">{selected.plan_count}</p></div><div className="rounded-lg border p-3"><p className="text-muted-foreground">离线授权</p><p className="mt-1 text-xl font-semibold">{selected.offline_days} 天</p></div></div>
                <div className="flex flex-wrap gap-2"><Button size="sm" variant="outline" onClick={() => setEditingSoftware(true)}>编辑信息</Button><Button size="sm" variant={selected.status === "ACTIVE" ? "destructive" : "default"} disabled={selected.status === "ARCHIVED"} onClick={() => void changeStatus(selected)}>{selected.status === "ACTIVE" ? "停用软件" : "启用软件"}</Button></div>
              </div>
            ) : (
              <form className="flex flex-col gap-4" onSubmit={saveSoftware}>
                <div className="flex flex-col gap-2"><label className="text-sm font-medium">软件名称</label><Input required maxLength={120} value={softwareForm.name} onChange={(e) => setSoftwareForm((s) => ({ ...s, name: e.target.value }))} placeholder="例如：桌面客户端" /></div>
                <div className="flex flex-col gap-2"><label className="text-sm font-medium">软件描述</label><Input maxLength={1000} value={softwareForm.description} onChange={(e) => setSoftwareForm((s) => ({ ...s, description: e.target.value }))} placeholder="软件用途或版本说明" /></div>
                <div className="flex flex-col gap-2"><label className="text-sm font-medium">离线授权天数（0–90）</label><Input type="number" min={0} max={90} required value={softwareForm.offline_days} onChange={(e) => setSoftwareForm((s) => ({ ...s, offline_days: e.target.value }))} /></div>
                <div className="flex items-center justify-between gap-3 rounded-md border p-3"><span className="text-sm">续费时再次消耗授权额度</span><Button type="button" size="sm" variant={softwareForm.renew_consumes_quota ? "default" : "outline"} onClick={() => setSoftwareForm((s) => ({ ...s, renew_consumes_quota: !s.renew_consumes_quota }))}>{softwareForm.renew_consumes_quota ? "已开启" : "未开启"}</Button></div>
                <div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => { setEditingSoftware(false); if (!selected) setSoftwareForm({ name: "", description: "", offline_days: "0", renew_consumes_quota: false }); }}>取消</Button><Button disabled={saving}>{saving ? <LoaderCircle className="size-4 animate-spin" /> : null}{editingSoftware ? "保存修改" : "创建软件"}</Button></div>
              </form>
            )}
          </CardContent>
        </Card>
      </div>

      {selected && selected.status !== "ARCHIVED" ? (
        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2 text-base"><PackagePlus className="size-4" />{selected.name} · 套餐管理</CardTitle><CardDescription>套餐只在所属软件内有效。永久套餐不设置到期时间。</CardDescription></CardHeader>
          <CardContent className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]">
            <div className="flex flex-col gap-3">
              {plans.length === 0 ? <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">该软件还没有套餐。</div> : plans.map((plan) => (
                <div key={plan.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
                  <div className="flex min-w-0 flex-col gap-1"><div className="flex flex-wrap items-center gap-2"><span className="font-medium">{plan.name}</span><Badge variant={plan.status === "ACTIVE" ? "success" : "outline"}>{plan.status === "ACTIVE" ? "启用" : "停用"}</Badge></div><span className="text-xs text-muted-foreground">{plan.plan_code} · {plan.duration_unit === "PERMANENT" ? "永久" : `${plan.duration_value} ${plan.duration_unit}`} · {plan.currency === "CNY" ? "¥" : ""}{plan.price}{plan.currency === "USDT" ? " U" : ""}</span></div>
                  <Button size="sm" variant="outline" onClick={() => startEditPlan(plan)}>编辑套餐</Button>
                </div>
              ))}
            </div>
            <form className="flex flex-col gap-3 rounded-lg border p-4" onSubmit={savePlan}>
              <div className="flex items-center justify-between gap-2"><p className="font-medium">{editingPlanId ? "编辑套餐" : "新增套餐"}</p>{editingPlanId ? <Button type="button" size="sm" variant="ghost" onClick={() => { setEditingPlanId(null); setPlanForm(emptyPlan); }}>取消编辑</Button> : null}</div>
              <div className="flex flex-col gap-2"><label className="text-sm font-medium">套餐名称</label><Input required maxLength={100} value={planForm.name} onChange={(e) => setPlanForm((s) => ({ ...s, name: e.target.value }))} placeholder="例如：月卡" /></div>
              <div className="flex flex-col gap-2"><label className="text-sm font-medium">套餐编码</label><Input required maxLength={32} value={planForm.plan_code} onChange={(e) => setPlanForm((s) => ({ ...s, plan_code: e.target.value.toUpperCase() }))} placeholder="MONTHLY" /></div>
              <div className="flex flex-col gap-2"><label className="text-sm font-medium">套餐时长类型</label><div className="grid grid-cols-4 gap-1">{(["DAY","MONTH","YEAR","PERMANENT"] as const).map((unit) => <Button key={unit} type="button" size="sm" variant={planForm.duration_unit === unit ? "default" : "outline"} onClick={() => setPlanForm((s) => ({ ...s, duration_unit: unit }))}>{({ DAY: "天", MONTH: "月", YEAR: "年", PERMANENT: "永久" })[unit]}</Button>)}</div></div>
              {planForm.duration_unit !== "PERMANENT" ? <div className="flex flex-col gap-2"><label className="text-sm font-medium">时长数值</label><Input type="number" min={1} max={1200} required value={planForm.duration_value} onChange={(e) => setPlanForm((s) => ({ ...s, duration_value: e.target.value }))} /></div> : null}
              <div className="flex flex-col gap-2"><label className="text-sm font-medium">价格</label><Input required inputMode="decimal" value={planForm.price} onChange={(e) => setPlanForm((s) => ({ ...s, price: e.target.value }))} placeholder="0.00" /></div>
              <div className="flex flex-col gap-2"><label className="text-sm font-medium">计价币种</label><div className="grid grid-cols-2 gap-2"><Button type="button" variant={planForm.currency === "CNY" ? "default" : "outline"} onClick={() => setPlanForm((s) => ({ ...s, currency: "CNY" }))}>¥ CNY</Button><Button type="button" variant={planForm.currency === "USDT" ? "default" : "outline"} onClick={() => setPlanForm((s) => ({ ...s, currency: "USDT" }))}>U · USDT</Button></div></div>
              <div className="flex flex-col gap-2"><label className="text-sm font-medium">套餐说明</label><Input maxLength={500} value={planForm.description} onChange={(e) => setPlanForm((s) => ({ ...s, description: e.target.value }))} placeholder="可选" /></div>
              <div className="flex flex-col gap-2"><label className="text-sm font-medium">状态</label><div className="grid grid-cols-2 gap-2"><Button type="button" variant={planForm.status === "ACTIVE" ? "default" : "outline"} onClick={() => setPlanForm((s) => ({ ...s, status: "ACTIVE" }))}>启用</Button><Button type="button" variant={planForm.status === "DISABLED" ? "default" : "outline"} onClick={() => setPlanForm((s) => ({ ...s, status: "DISABLED" }))}>停用</Button></div></div>
              <Button disabled={saving}>{saving ? <LoaderCircle className="size-4 animate-spin" /> : <Check className="size-4" />}{editingPlanId ? "保存套餐" : "创建套餐"}</Button>
            </form>
          </CardContent>
        </Card>
      ) : null}
    </section>
  );
}
