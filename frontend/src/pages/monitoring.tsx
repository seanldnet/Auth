import { useCallback, useEffect, useState } from "react";
import { Activity, CheckCircle2, LoaderCircle, RefreshCw, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
type Health={overall:string;api:string;database:string;checked_at:string};
export function MonitoringPage(){const [data,setData]=useState<Health|null>(null);const [loading,setLoading]=useState(true);const [error,setError]=useState("");
 const load=useCallback(async()=>{setLoading(true);setError("");try{const r=await fetch("/api/admin/system-health",{credentials:"include",headers:{Accept:"application/json"}});const j=await r.json();if(!r.ok||!j.success)throw new Error(j.message||"检查失败");setData(j.data);}catch(e){setError(e instanceof Error?e.message:"读取监控状态失败");}finally{setLoading(false);}},[]);
 useEffect(()=>{void load();},[load]);
 const status=(value:string)=>value==="ok"?<span className="inline-flex items-center gap-2 text-emerald-600"><CheckCircle2 className="size-4"/>正常</span>:<span className="inline-flex items-center gap-2 text-destructive"><XCircle className="size-4"/>异常</span>;
 return <section className="flex flex-col gap-6"><div className="flex flex-wrap items-start justify-between gap-4"><div className="flex flex-col gap-2"><p className="text-sm font-medium text-primary">系统管理</p><h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">系统监控</h1><p className="text-sm text-muted-foreground">检查当前 API 服务和数据库连通性。此页不是区域压测或完整基础设施监控面板。</p></div><Button variant="outline" onClick={()=>void load()} disabled={loading}><RefreshCw className="size-4"/>重新检查</Button></div>
 {error?<div role="alert" className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{error}</div>:null}
 <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{[["API 服务",data?.api],["MySQL 数据库",data?.database],["整体状态",data?.overall]].map(([label,value])=><Card key={label}><CardHeader><CardTitle className="text-base">{label}</CardTitle><CardDescription>实时连通性检查</CardDescription></CardHeader><CardContent className="flex items-center justify-between">{loading?<LoaderCircle className="size-5 animate-spin text-muted-foreground"/>:value?status(value):"—"}<Activity className="size-5 text-muted-foreground"/></CardContent></Card>)}</div>
 <Card><CardHeader><CardTitle className="text-base">检查信息</CardTitle><CardDescription>状态由本次请求实时检测，不代表历史可用性统计。</CardDescription></CardHeader><CardContent className="text-sm text-muted-foreground">最近检查：{data?.checked_at||"尚未完成"}</CardContent></Card>
 </section>;}
