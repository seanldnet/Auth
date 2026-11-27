import { useCallback, useEffect, useState, type FormEvent } from "react";
import { Copy, LoaderCircle, RefreshCw, UserPlus, Users } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

type User = { id: number; username: string; role: "ADMIN"|"AGENT"|"SUB_AGENT"|"USER"; parent_id: number|null; parent_username?: string|null; invite_code?: string|null; status: "ACTIVE"|"DISABLED"|"LOCKED"; must_change_password?: boolean; last_login_at?: string|null; created_at?: string; assigned_at?: string|null };
type PageData = { items: User[]; total: number; page: number; page_size: number };
type TransferRequest = { id:number; user_id:number; username:string; from_parent_username:string|null; requested_parent_username:string|null; request_reason:string|null; status:string; created_at:string };
async function api<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, { credentials: "include", headers: { Accept: "application/json", "Content-Type": "application/json" }, ...init });
  const result = await response.json();
  if (!response.ok || !result.success) throw new Error(result.message || "请求失败");
  return result.data as T;
}
const roleLabel: Record<string,string> = { ADMIN:"管理员", AGENT:"代理", SUB_AGENT:"下级代理", USER:"用户" };
const statusLabel: Record<string,string> = { ACTIVE:"正常", DISABLED:"已停用", LOCKED:"已锁定" };

export function UsersPage() {
  const [actor, setActor] = useState<{role:string}|null>(null);
  const [items, setItems] = useState<User[]>([]);
  const [agents, setAgents] = useState<User[]>([]);
  const [transferRequests, setTransferRequests] = useState<TransferRequest[]>([]);
  const [role, setRole] = useState("");
  const [keyword, setKeyword] = useState("");
  const [poolOnly, setPoolOnly] = useState(false);
  const [statusFilter, setStatusFilter] = useState("");
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [username, setUsername] = useState("");
  const [newRole, setNewRole] = useState("USER");
  const [password, setPassword] = useState("");
  const [created, setCreated] = useState<User & {initial_password?:string}|null>(null);
  const [assignTarget, setAssignTarget] = useState<Record<number,string>>({});
  const [transferTarget, setTransferTarget] = useState<Record<number,string>>({});

  const load = useCallback(async () => {
    setLoading(true); setError("");
    try {
      const [me, data] = await Promise.all([
        api<{user:{role:string}}>("/api/auth/me"),
        api<PageData>(`/api/users?${new URLSearchParams({page:String(page),page_size:"20",role,keyword,status:statusFilter,pool:poolOnly?"1":""})}`),
      ]);
      setActor(me.user); setItems(data.items); setTotal(data.total);
      if (me.user.role === "ADMIN") {
        const agentsData = await api<PageData>("/api/users?role=AGENT&page_size=100");
        const subAgentsData = await api<PageData>("/api/users?role=SUB_AGENT&page_size=100");
        setAgents([...agentsData.items, ...subAgentsData.items]);
        const requests = await api<{items:TransferRequest[];total:number}>("/api/admin/user-transfer-requests?status=PENDING&page_size=100");
        setTransferRequests(requests.items);
      }
    } catch (cause) { setError(cause instanceof Error ? cause.message : "读取用户列表失败"); }
    finally { setLoading(false); }
  }, [page, role, keyword, poolOnly, statusFilter]);

  useEffect(() => { void load(); }, [load]);

  const allowedRoles = actor?.role === "ADMIN" ? ["AGENT","USER"] : actor?.role === "AGENT" ? ["SUB_AGENT","USER"] : actor?.role === "SUB_AGENT" ? ["USER"] : [];
  useEffect(() => { if (!allowedRoles.includes(newRole)) setNewRole(allowedRoles[0] || "USER"); }, [actor?.role]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError(""); setNotice(""); setCreated(null);
    try {
      const result = await api<User & {initial_password?:string}>("/api/users", { method:"POST", body:JSON.stringify({username,role:newRole,password}) });
      setCreated(result); setNotice("账号创建成功。若系统生成了初始密码，请立即复制保存。");
      setUsername(""); setPassword(""); setPage(1); await load();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "创建账号失败"); }
    finally { setSaving(false); }
  }

  async function assign(userId:number) {
    const agentId = Number(assignTarget[userId] || 0);
    if (!agentId) { setError("请先选择分配对象。"); return; }
    try {
      await api<User>(`/api/admin/users/${userId}/assign`, {method:"POST",body:JSON.stringify({agent_id:agentId})});
      setNotice("用户已分配给指定代理。"); await load();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "分配失败"); }
  }

  async function reviewTransfer(id:number, decision:"APPROVED"|"REJECTED") {
    try {
      await api<User>("/api/admin/user-transfer-requests/" + id + "/review", {method:"PATCH",body:JSON.stringify({decision})});
      setNotice(decision==="APPROVED"?"归属变更已审批通过。":"归属变更申请已驳回。");
      await load();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "审批失败"); }
  }

  async function requestTransfer(userId:number) {
    const target = transferTarget[userId];
    if (target === undefined || target === "") { setError("请选择新的归属代理或公共用户池。"); return; }
    const reason = window.prompt("请输入归属变更原因（可留空）：") || "";
    try {
      await api<User>(`/api/admin/users/${userId}/transfer-requests`, {
        method:"POST",
        body:JSON.stringify({target_parent_id:target === "POOL" ? null : Number(target), reason}),
      });
      setNotice("归属变更申请已创建，请在待审批列表中完成审批。");
      await load();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "创建归属变更申请失败"); }
  }

  async function changeStatus(item:User, status:"ACTIVE"|"DISABLED"|"LOCKED") {
    try {
      await api<User>("/api/admin/users/" + item.id + "/status", {method:"PATCH",body:JSON.stringify({status})});
      setNotice("账号 " + item.username + " 状态已更新。"); await load();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "账号状态更新失败"); }
  }

  const pages = Math.max(1,Math.ceil(total/20));
  return <section className="flex flex-col gap-6">
    <div className="flex flex-wrap items-start justify-between gap-4">
      <div className="flex flex-col gap-2"><p className="text-sm font-medium text-primary">组织与账号</p><h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">用户与代理</h1><p className="max-w-3xl text-sm leading-6 text-muted-foreground">按管理员、代理、下级代理和用户的层级管理账号。代理只能管理自己的下级范围，公共用户由管理员统一分配。</p></div>
      <Button variant="outline" onClick={()=>void load()} disabled={loading}><RefreshCw className="size-4"/>刷新</Button>
    </div>
    {error ? <div role="alert" className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{error}</div> : null}
    {notice ? <div className="rounded-lg border border-emerald-600/20 bg-emerald-600/5 p-3 text-sm text-emerald-700">{notice}</div> : null}
    {actor?.role==="ADMIN" ? <Card><CardHeader><CardTitle className="text-base">待审批的归属变更</CardTitle><CardDescription>用户提交的代理归属变更必须由管理员最终审批；审批时会再次校验用户当前归属，防止过期申请覆盖新数据。</CardDescription></CardHeader><CardContent className="flex flex-col gap-3">{transferRequests.length===0?<p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">当前没有待审批申请。</p>:transferRequests.map(request=><div key={request.id} className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"><div className="flex flex-col gap-1"><strong>{request.username}</strong><span className="text-xs text-muted-foreground">当前归属：{request.from_parent_username||"公共用户池"} → 申请归属：{request.requested_parent_username||"公共用户池"}</span><span className="text-xs text-muted-foreground">原因：{request.request_reason||"未填写"} · 提交：{request.created_at}</span></div><div className="flex gap-2"><Button size="sm" onClick={()=>void reviewTransfer(request.id,"APPROVED")}>批准</Button><Button size="sm" variant="outline" onClick={()=>void reviewTransfer(request.id,"REJECTED")}>驳回</Button></div></div>)}</CardContent></Card> : null}
    {created ? <Card className="border-primary/30"><CardHeader><CardTitle className="text-base">新账号创建成功</CardTitle><CardDescription>请将初始密码安全交付给账号持有人。</CardDescription></CardHeader><CardContent className="flex flex-col gap-2 text-sm"><p>账号：<strong>{created.username}</strong> · {roleLabel[created.role]}</p>{created.initial_password ? <div className="flex flex-wrap items-center gap-2"><code className="rounded bg-muted px-3 py-2">{created.initial_password}</code><Button size="sm" variant="outline" onClick={()=>void navigator.clipboard.writeText(created.initial_password!)}><Copy className="size-4"/>复制初始密码</Button></div> : null}{created.invite_code ? <div className="flex flex-wrap items-center gap-2"><span>邀请码：</span><code className="rounded bg-muted px-3 py-2">{created.invite_code}</code><Button size="sm" variant="outline" onClick={()=>void navigator.clipboard.writeText(created.invite_code!)}><Copy className="size-4"/>复制邀请码</Button></div> : null}<Button size="sm" variant="ghost" className="self-start" onClick={()=>setCreated(null)}>关闭</Button></CardContent></Card> : null}
    {allowedRoles.length > 0 ? <Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><UserPlus className="size-4"/>创建账号</CardTitle><CardDescription>新建账号默认启用；留空密码时系统生成一次性初始密码，并要求首次登录修改。</CardDescription></CardHeader><CardContent><form onSubmit={create} className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-2"><div className="flex flex-col gap-2"><label className="text-sm font-medium">登录账号</label><Input value={username} onChange={e=>setUsername(e.target.value)} minLength={3} maxLength={64} placeholder="3–64 位字母、数字或 ._-" required/></div><div className="flex flex-col gap-2"><label className="text-sm font-medium">账号角色</label><div className="flex flex-wrap gap-2">{allowedRoles.map(item=><Button type="button" key={item} size="sm" variant={newRole===item?"default":"outline"} onClick={()=>setNewRole(item)}>{roleLabel[item]}</Button>)}</div></div></div>
      <div className="flex flex-col gap-2 sm:max-w-md"><label className="text-sm font-medium">初始密码（可留空自动生成）</label><Input type="password" value={password} onChange={e=>setPassword(e.target.value)} minLength={password?12:undefined} maxLength={1024} placeholder="至少 12 位；留空自动生成"/></div>
      <Button type="submit" disabled={saving||!username.trim()||Boolean(password&&password.length<12)} className="self-start">{saving?<LoaderCircle className="size-4 animate-spin"/>:<UserPlus className="size-4"/>}{saving?"正在创建…":"创建账号"}</Button>
    </form></CardContent></Card> : null}
    <Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><Users className="size-4"/>账号列表</CardTitle><CardDescription>共 {total} 个账号。列表内容由后端按当前登录角色执行数据范围限制。</CardDescription></CardHeader><CardContent className="flex flex-col gap-4">
      <div className="flex flex-col gap-3"><Input value={keyword} onChange={e=>{setKeyword(e.target.value);setPage(1)}} placeholder="搜索登录账号"/><div className="flex flex-wrap gap-2">{["","AGENT","SUB_AGENT","USER"].filter(item=>actor?.role==="ADMIN"||item!=="AGENT").map(item=><Button key={item||"ALL"} size="sm" variant={role===item?"default":"outline"} onClick={()=>{setRole(item);setPage(1)}}>{item?roleLabel[item]:"全部角色"}</Button>)}</div>{actor?.role==="ADMIN"?<div className="flex flex-wrap items-center gap-2"><Button size="sm" variant={poolOnly?"default":"outline"} onClick={()=>{setPoolOnly(v=>!v);setPage(1)}}>{poolOnly?"正在查看公共用户池":"查看公共用户池"}</Button><span className="text-xs text-muted-foreground">未填写邀请码自主注册的用户会进入公共用户池</span></div>:null}<div className="flex flex-wrap gap-2">{[["","全部状态"],["ACTIVE","正常"],["DISABLED","已停用"],["LOCKED","已锁定"]].map(([value,label])=><Button key={value||"all-status"} size="sm" variant={statusFilter===value?"default":"outline"} onClick={()=>{setStatusFilter(value);setPage(1)}}>{label}</Button>)}</div></div>
      {loading?<div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground"><LoaderCircle className="size-4 animate-spin"/>正在读取账号</div>:items.length===0?<div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">暂无符合条件的账号。</div>:<div className="flex flex-col gap-3">{items.map(item=><div key={item.id} className="flex flex-col gap-3 rounded-lg border p-4 lg:flex-row lg:items-center lg:justify-between"><div className="flex min-w-0 flex-col gap-1"><div className="flex flex-wrap items-center gap-2"><strong>{item.username}</strong><Badge variant="outline">{roleLabel[item.role]}</Badge><Badge variant={item.status==="ACTIVE"?"success":"warning"}>{statusLabel[item.status]}</Badge></div><span className="text-xs text-muted-foreground">上级：{item.parent_username||"公共用户池 / 平台直属"} · 创建：{item.created_at||"—"} · 最近登录：{item.last_login_at||"尚未登录"}</span>{item.invite_code?<span className="text-xs text-muted-foreground">邀请码：{item.invite_code}</span>:null}</div>
        {actor?.role==="ADMIN"&&poolOnly&&item.role==="USER"?<div className="flex flex-wrap items-center gap-2"><span className="text-xs text-muted-foreground">分配给：</span>{agents.filter(a=>a.status==="ACTIVE").map(a=><Button key={a.id} size="sm" variant={assignTarget[item.id]===String(a.id)?"default":"outline"} onClick={()=>setAssignTarget(s=>({...s,[item.id]:String(a.id)}))}>{a.username} · {roleLabel[a.role]}</Button>)}<Button size="sm" disabled={!assignTarget[item.id]} onClick={()=>void assign(item.id)}>确认分配</Button></div>:null}{actor?.role==="ADMIN"&&item.role==="USER"?<div className="flex flex-wrap items-center gap-2"><span className="text-xs text-muted-foreground">调整归属：</span><Select value={transferTarget[item.id]||"__none"} onValueChange={v=>setTransferTarget(s=>({...s,[item.id]:v==="__none"?"":v}))}><SelectTrigger className="h-9 text-xs"><SelectValue placeholder="选择代理 / 公共池" /></SelectTrigger><SelectContent><SelectItem value="__none">选择代理 / 公共池</SelectItem><SelectItem value="POOL">公共用户池</SelectItem>{agents.filter(a=>a.status==="ACTIVE").map(a=><SelectItem key={a.id} value={String(a.id)}>{a.username} · {roleLabel[a.role]}</SelectItem>)}</SelectContent></Select><Button size="sm" variant="outline" disabled={transferTarget[item.id]===undefined||transferTarget[item.id]===""} onClick={()=>void requestTransfer(item.id)}>发起变更申请</Button></div>:null}{actor?.role==="ADMIN"&&item.role!=="ADMIN"?<div className="flex flex-wrap gap-2">{item.status==="ACTIVE"?<><Button size="sm" variant="outline" onClick={()=>void changeStatus(item,"DISABLED")}>停用</Button><Button size="sm" variant="outline" onClick={()=>void changeStatus(item,"LOCKED")}>锁定</Button></>:<Button size="sm" onClick={()=>void changeStatus(item,"ACTIVE")}>恢复账号</Button>}</div>:null}
      </div>)}</div>}
      <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4 text-sm text-muted-foreground"><span>第 {page} / {pages} 页</span><div className="flex gap-2"><Button size="sm" variant="outline" disabled={page<=1||loading} onClick={()=>setPage(p=>p-1)}>上一页</Button><Button size="sm" variant="outline" disabled={page>=pages||loading} onClick={()=>setPage(p=>p+1)}>下一页</Button></div></div>
    </CardContent></Card>
  </section>;
}
