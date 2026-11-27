import { useEffect, useState, type FormEvent } from "react";
import { LoaderCircle, RefreshCw } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

type User = { id: number; username: string; role: string };
type Software = { id: number; name: string; status: string };
type Plan = { id: number; name: string; plan_code: string; status: string };
type Quota = { id: number; user_id: number; username: string; role: string; software_id: number; software_name: string; plan_id: number; plan_name: string; available_quantity: number; allocated_quantity: number; consumed_quantity: number };

async function read<T>(url: string): Promise<T> {
  const response = await fetch(url, { credentials: "include", headers: { Accept: "application/json" } });
  const result = await response.json();
  if (!response.ok || !result.success) throw new Error(result.message || "读取数据失败");
  return result.data as T;
}

export function QuotasPage() {
  const [users, setUsers] = useState<User[]>([]);
  const [software, setSoftware] = useState<Software[]>([]);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [items, setItems] = useState<Quota[]>([]);
  const [userId, setUserId] = useState("");
  const [softwareId, setSoftwareId] = useState("");
  const [planId, setPlanId] = useState("");
  const [quantity, setQuantity] = useState("1");
  const [description, setDescription] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  async function loadQuotas() {
    const data = await read<{ items: Quota[] }>("/api/license-quotas?page=1&page_size=100");
    setItems(data.items);
  }

  useEffect(() => {
    Promise.all([
      read<{ items: User[] }>("/api/users?role=AGENT&page=1&page_size=100"),
      read<{ items: User[] }>("/api/users?role=SUB_AGENT&page=1&page_size=100"),
      read<Software[]>("/api/admin/software"),
      loadQuotas(),
    ]).then(([agents, subs, apps]) => {
      setUsers([...agents.items, ...subs.items]);
      setSoftware(apps.filter((app) => app.status === "ACTIVE"));
    }).catch((cause) => setError(cause instanceof Error ? cause.message : "初始化数据失败"))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (!softwareId) { setPlans([]); setPlanId(""); return; }
    read<Plan[]>("/api/admin/software/" + softwareId + "/plans")
      .then((data) => {
        const active = data.filter((plan) => plan.status === "ACTIVE");
        setPlans(active);
        setPlanId(active[0] ? String(active[0].id) : "");
      })
      .catch((cause) => setError(cause instanceof Error ? cause.message : "读取套餐失败"));
  }, [softwareId]);

  async function allocate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setNotice("");
    const amount = Number(quantity);
    if (!userId || !softwareId || !planId || !Number.isSafeInteger(amount) || amount < 1 || amount > 1000000) {
      setError("请选择代理、软件、套餐，并填写 1 至 1,000,000 的整数额度。");
      return;
    }
    setSaving(true);
    try {
      const key = crypto.randomUUID().replaceAll("-", "");
      const response = await fetch("/api/admin/license-quotas", {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json", Accept: "application/json", "Idempotency-Key": key },
        body: JSON.stringify({ user_id: Number(userId), software_id: Number(softwareId), plan_id: Number(planId), quantity: amount, description }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "分配额度失败");
      setNotice("额度分配成功。");
      setDescription("");
      await loadQuotas();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "分配额度失败");
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium text-primary">授权业务 / 额度管理</p>
        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">代理授权额度</h1>
        <p className="text-sm text-muted-foreground">额度按代理、软件和套餐分别记录；只有管理员可以分配，代理不能向下级转额度。</p>
      </div>
      <Card>
        <CardHeader><CardTitle className="text-base">分配授权额度</CardTitle><CardDescription>分配操作会写入额度流水，并使用幂等键防止重复提交。</CardDescription></CardHeader>
        <CardContent>
          <form onSubmit={allocate} className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <label className="flex flex-col gap-2 text-sm">代理 / 下级代理
              <Select value={userId || "__none"} onValueChange={v=>setUserId(v==="__none"?"":v)}><SelectTrigger><SelectValue placeholder="请选择代理" /></SelectTrigger><SelectContent><SelectItem value="__none">请选择代理</SelectItem>{users.map(user=><SelectItem key={user.id} value={String(user.id)}>{user.username}（{user.role === "AGENT" ? "代理" : "下级代理"}）</SelectItem>)}</SelectContent></Select>
            </label>
            <label className="flex flex-col gap-2 text-sm">软件
              <Select value={softwareId || "__none"} onValueChange={v=>setSoftwareId(v==="__none"?"":v)}><SelectTrigger><SelectValue placeholder="请选择软件" /></SelectTrigger><SelectContent><SelectItem value="__none">请选择软件</SelectItem>{software.map(app=><SelectItem key={app.id} value={String(app.id)}>{app.name}</SelectItem>)}</SelectContent></Select>
            </label>
            <label className="flex flex-col gap-2 text-sm">套餐
              <Select value={planId || "__none"} onValueChange={v=>setPlanId(v==="__none"?"":v)} disabled={!plans.length}><SelectTrigger><SelectValue placeholder="请选择套餐" /></SelectTrigger><SelectContent><SelectItem value="__none">请选择套餐</SelectItem>{plans.map(plan=><SelectItem key={plan.id} value={String(plan.id)}>{plan.name}（{plan.plan_code}）</SelectItem>)}</SelectContent></Select>
            </label>
            <label className="flex flex-col gap-2 text-sm">分配数量
              <Input type="number" min="1" max="1000000" step="1" value={quantity} onChange={(e) => setQuantity(e.target.value)} />
            </label>
            <label className="flex flex-col gap-2 text-sm md:col-span-2">备注（可选）
              <Input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="例如：首批代理额度" />
            </label>
            <div className="flex flex-wrap items-center gap-3 md:col-span-2">
              <Button type="submit" disabled={saving || loading || !users.length || !software.length}>{saving ? <LoaderCircle className="mr-2 size-4 animate-spin" /> : null}确认分配</Button>
              {notice ? <span className="text-sm text-emerald-600">{notice}</span> : null}
              {error ? <span className="text-sm text-destructive">{error}</span> : null}
            </div>
          </form>
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-3">
          <div><CardTitle className="text-base">额度账户</CardTitle><CardDescription>当前每个代理在每个软件、套餐下的可用额度。</CardDescription></div>
          <Button variant="outline" size="sm" onClick={() => { setLoading(true); loadQuotas().catch((e) => setError(e.message)).finally(() => setLoading(false)); }}><RefreshCw className="mr-2 size-4" />刷新</Button>
        </CardHeader>
        <CardContent>
          {loading ? <div className="flex items-center gap-2 py-6 text-sm text-muted-foreground"><LoaderCircle className="size-4 animate-spin" />正在读取额度…</div> : items.length === 0 ? <p className="py-6 text-sm text-muted-foreground">暂无额度记录。</p> : (
            <div className="overflow-x-auto">
              <Table className="w-full min-w-[720px] text-sm">
                <TableHeader><TableRow className="border-b text-left text-muted-foreground"><TableHead className="p-3">代理</TableHead><TableHead className="p-3">软件 / 套餐</TableHead><TableHead className="p-3">可用</TableHead><TableHead className="p-3">累计分配</TableHead><TableHead className="p-3">已消耗</TableHead><TableHead className="p-3">状态</TableHead></TableRow></TableHeader>
                <TableBody>{items.map((item) => <TableRow key={item.id} className="border-b last:border-0"><TableCell className="p-3"><span className="font-medium">{item.username}</span><p className="text-xs text-muted-foreground">{item.role}</p></TableCell><TableCell className="p-3">{item.software_name}<p className="text-xs text-muted-foreground">{item.plan_name}</p></TableCell><TableCell className="p-3 font-semibold tabular-nums">{item.available_quantity}</TableCell><TableCell className="p-3 tabular-nums">{item.allocated_quantity}</TableCell><TableCell className="p-3 tabular-nums">{item.consumed_quantity}</TableCell><TableCell className="p-3"><Badge variant="secondary">正常</Badge></TableCell></TableRow>)}</TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </section>
  );
}
