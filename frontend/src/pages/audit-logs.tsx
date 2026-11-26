import { useCallback, useEffect, useState } from "react";
import { ClipboardList, LoaderCircle, RefreshCw, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

type Audit = { id:number; actor_user_id:number|null; actor_username:string|null; event_type:string; target_type:string|null; target_id:string|null; ip_address:string|null; metadata:string|null; created_at:string };
type Result = { items:Audit[]; total:number; page:number; page_size:number };
async function api<T>(url:string):Promise<T>{const r=await fetch(url,{credentials:"include",headers:{Accept:"application/json"}});const j=await r.json();if(!r.ok||!j.success)throw new Error(j.message||"读取失败");return j.data as T;}
export function AuditLogsPage(){
 const [items,setItems]=useState<Audit[]>([]);const [total,setTotal]=useState(0);const [page,setPage]=useState(1);const [keyword,setKeyword]=useState("");const [eventType,setEventType]=useState("");const [loading,setLoading]=useState(true);const [error,setError]=useState("");
 const load=useCallback(async()=>{setLoading(true);setError("");try{const p=new URLSearchParams({page:String(page),page_size:"30",keyword,event_type:eventType});const d=await api<Result>("/api/admin/audit-logs?"+p);setItems(d.items);setTotal(d.total);}catch(e){setError(e instanceof Error?e.message:"读取审计日志失败");}finally{setLoading(false);}},[page,keyword,eventType]);
 useEffect(()=>{void load();},[load]);
 const pages=Math.max(1,Math.ceil(total/30));
 return <section className="flex flex-col gap-6">
  <div className="flex flex-wrap items-start justify-between gap-4"><div className="flex flex-col gap-2"><p className="text-sm font-medium text-primary">安全中心</p><h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">操作审计</h1><p className="text-sm text-muted-foreground">查询用户、密钥、授权和管理操作的审计记录。敏感凭据不会在此页面展示。</p></div><Button variant="outline" onClick={()=>void load()} disabled={loading}><RefreshCw className="size-4"/>刷新</Button></div>
  {error?<div role="alert" className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{error}</div>:null}
  <Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><ClipboardList className="size-4"/>审计记录</CardTitle><CardDescription>共 {total} 条记录，按最新操作时间倒序排列。</CardDescription></CardHeader><CardContent className="flex flex-col gap-4">
   <div className="grid gap-3 sm:grid-cols-[1fr_240px_auto]"><Input value={keyword} onChange={e=>{setKeyword(e.target.value);setPage(1);}} placeholder="搜索操作人、事件或目标 ID"/><Input value={eventType} onChange={e=>{setEventType(e.target.value);setPage(1);}} placeholder="事件类型（可选）"/><Button variant="outline" onClick={()=>void load()}><Search className="size-4"/>查询</Button></div>
   {loading?<div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground"><LoaderCircle className="size-4 animate-spin"/>正在读取</div>:items.length===0?<p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">暂无审计记录。</p>:<div className="overflow-x-auto"><table className="w-full min-w-[900px] text-sm"><thead><tr className="border-b text-left text-muted-foreground"><th className="p-3">时间</th><th className="p-3">操作人</th><th className="p-3">事件</th><th className="p-3">目标</th><th className="p-3">IP 地址</th><th className="p-3">详情</th></tr></thead><tbody>{items.map(x=><tr key={x.id} className="border-b last:border-0"><td className="p-3 whitespace-nowrap">{x.created_at}</td><td className="p-3">{x.actor_username||"系统"}</td><td className="p-3 font-medium">{x.event_type}</td><td className="p-3">{x.target_type||"—"} {x.target_id||""}</td><td className="p-3">{x.ip_address||"—"}</td><td className="max-w-[280px] truncate p-3 text-xs text-muted-foreground">{x.metadata||"—"}</td></tr>)}</tbody></table></div>}
   <div className="flex items-center justify-between border-t pt-4 text-sm text-muted-foreground"><span>第 {page} / {pages} 页</span><div className="flex gap-2"><Button size="sm" variant="outline" disabled={page<=1||loading} onClick={()=>setPage(x=>x-1)}>上一页</Button><Button size="sm" variant="outline" disabled={page>=pages||loading} onClick={()=>setPage(x=>x+1)}>下一页</Button></div></div>
  </CardContent></Card>
 </section>;
}
