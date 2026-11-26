import { useCallback, useEffect, useMemo, useState, type FormEvent } from "react";
import { Copy, KeyRound, LoaderCircle, RefreshCw, ShieldCheck, Trash2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

type ApiKey = {
  id:number; key_id:string; owner_user_id:number; owner_username:string; name:string;
  permissions:string[]|string; ip_allowlist:string[]|string|null; status:"ACTIVE"|"REVOKED"|"EXPIRED";
  expires_at:string|null; last_used_at:string|null; created_at:string;
};
type ApiKeyPage = {items:ApiKey[];total:number};
type Session = {user:{id:number;role:"ADMIN"|"AGENT"|"SUB_AGENT"|"USER"}};
type CreatedKey = {key_id:string;api_key:string;name:string;warning:string};
const permissionLabels:Record<string,string> = {
  "*":"全站权限", "license.create":"开通授权", "license.replace":"更换授权",
  "license.read":"查询授权", "user.read":"查询用户", "sub_agent.read":"查询下级代理",
};
const agentPermissions = ["license.create","license.replace","license.read","user.read","sub_agent.read"];

async function api<T>(url:string, init?:RequestInit):Promise<T>{
  const response=await fetch(url,{credentials:"include",headers:{Accept:"application/json","Content-Type":"application/json",...(init?.headers||{})},...init});
  const result=await response.json();
  if(!response.ok||!result.success) throw new Error(result.message||"请求失败");
  return result.data as T;
}
function parseArray(value:string[]|string|null|undefined):string[]{
  if(Array.isArray(value)) return value;
  if(typeof value==="string"){try{const parsed=JSON.parse(value);return Array.isArray(parsed)?parsed:[];}catch{return [];}}
  return [];
}
const dateText=(value:string|null)=>value?new Date(value.replace(" ","T")+"Z").toLocaleString():"永不过期";

export function ApiKeysPage(){
  const [role,setRole]=useState<Session["user"]["role"]>("USER");
  const [keys,setKeys]=useState<ApiKey[]>([]);
  const [name,setName]=useState("");
  const [permissions,setPermissions]=useState<string[]>([]);
  const [ipText,setIpText]=useState("");
  const [expiresAt,setExpiresAt]=useState("");
  const [created,setCreated]=useState<CreatedKey|null>(null);
  const [loading,setLoading]=useState(true);
  const [saving,setSaving]=useState(false);
  const [error,setError]=useState("");
  const [notice,setNotice]=useState("");
  const availablePermissions=useMemo(()=>role==="ADMIN"?["*",...agentPermissions]:role==="AGENT"?agentPermissions:agentPermissions.filter(item=>item!=="sub_agent.read"),[role]);

  const load=useCallback(async()=>{
    setLoading(true);setError("");
    try{
      const [session,data]=await Promise.all([api<Session>("/api/auth/me"),api<ApiKeyPage>("/api/api-keys?page=1&page_size=100")]);
      setRole(session.user.role);setKeys(data.items);
    }catch(cause){setError(cause instanceof Error?cause.message:"读取 API 密钥失败");}
    finally{setLoading(false);}
  },[]);
  useEffect(()=>{void load();},[load]);
  useEffect(()=>{setPermissions(role==="ADMIN"?["*"]:availablePermissions);},[role,availablePermissions]);

  function togglePermission(permission:string){
    setPermissions(current=>{
      if(permission==="*") return current.includes("*")?[]:["*"];
      const next=current.filter(item=>item!=="*");
      return next.includes(permission)?next.filter(item=>item!==permission):[...next,permission];
    });
  }

  async function create(event:FormEvent<HTMLFormElement>){
    event.preventDefault();setSaving(true);setError("");setNotice("");setCreated(null);
    try{
      const result=await api<CreatedKey>("/api/api-keys",{method:"POST",body:JSON.stringify({
        name,permissions,ip_allowlist:ipText.split(",").map(item=>item.trim()).filter(Boolean),
        expires_at:expiresAt?new Date(expiresAt).toISOString():"",
      })});
      setCreated(result);setName("");setIpText("");setExpiresAt("");
      setNotice("API Key 创建成功。请立即复制完整密钥，关闭页面后无法再次查看。");
      await load();
    }catch(cause){setError(cause instanceof Error?cause.message:"创建 API Key 失败");}
    finally{setSaving(false);}
  }

  async function revoke(keyId:string){
    if(!window.confirm("撤销后该 API Key 将立即失效，确定继续吗？"))return;
    setError("");setNotice("");
    try{await api("/api/api-keys/"+encodeURIComponent(keyId)+"/revoke",{method:"PATCH"});setNotice("API Key 已撤销。");await load();}
    catch(cause){setError(cause instanceof Error?cause.message:"撤销失败");}
  }

  async function copy(value:string){
    try{await navigator.clipboard.writeText(value);setNotice("已复制到剪贴板。");}
    catch{setError("复制失败，请手动选择并复制密钥。");}
  }

  return <div className="flex flex-col gap-6">
    <div className="flex flex-wrap items-start justify-between gap-3">
      <div><h1 className="text-2xl font-semibold tracking-tight">API 密钥</h1><p className="mt-1 text-sm text-muted-foreground">管理管理员或代理 API 凭据、权限范围与来源 IP。</p></div>
      <Button variant="outline" onClick={()=>void load()} disabled={loading}><RefreshCw className="size-4"/>刷新</Button>
    </div>
    {error&&<div role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">{error}</div>}
    {notice&&<div role="status" className="rounded-md border border-border bg-muted/50 px-4 py-3 text-sm">{notice}</div>}
    {created&&<Card className="border-primary/40"><CardHeader><CardTitle className="flex items-center gap-2 text-base"><ShieldCheck className="size-5 text-primary"/>新密钥已生成</CardTitle><CardDescription>{created.warning}</CardDescription></CardHeader><CardContent className="flex flex-col gap-3">
      <div className="break-all rounded-md bg-muted p-3 font-mono text-sm">{created.api_key}</div>
      <div><Button onClick={()=>void copy(created.api_key)}><Copy className="size-4"/>复制完整密钥</Button></div>
    </CardContent></Card>}
    <Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><KeyRound className="size-5"/>创建 API Key</CardTitle><CardDescription>密钥 Secret 仅在创建成功时返回一次。请使用 HTTPS 并按 API 文档计算 HMAC-SHA256 签名。</CardDescription></CardHeader>
      <CardContent><form onSubmit={create} className="flex flex-col gap-4">
        <div className="grid gap-2 md:grid-cols-2">
          <label className="flex flex-col gap-1.5 text-sm font-medium">密钥名称<Input value={name} onChange={event=>setName(event.target.value)} maxLength={80} required placeholder="例如：生产环境授权接入"/></label>
          <label className="flex flex-col gap-1.5 text-sm font-medium">过期时间（可选）<Input type="datetime-local" value={expiresAt} onChange={event=>setExpiresAt(event.target.value)}/></label>
        </div>
        <div className="flex flex-col gap-2"><span className="text-sm font-medium">权限范围</span><div className="flex flex-wrap gap-2">{availablePermissions.map(permission=><Button key={permission} type="button" size="sm" variant={permissions.includes(permission)?"default":"outline"} onClick={()=>togglePermission(permission)}>{permissionLabels[permission]}</Button>)}</div><p className="text-xs text-muted-foreground">代理与下级代理不能申请全站权限；下级代理不能查询下级代理。服务端仍会强制执行资源归属限制。</p></div>
        <label className="flex flex-col gap-1.5 text-sm font-medium">IP 白名单（可选，多个地址用英文逗号分隔）<Input value={ipText} onChange={event=>setIpText(event.target.value)} placeholder="203.0.113.10, 2001:db8::1"/></label>
        <div><Button type="submit" disabled={saving||!name.trim()||permissions.length===0}>{saving?<LoaderCircle className="size-4 animate-spin"/>:<KeyRound className="size-4"/>}创建密钥</Button></div>
      </form></CardContent>
    </Card>
    <Card><CardHeader><CardTitle className="text-base">已创建的密钥</CardTitle><CardDescription>管理员可以查看全站密钥元数据；代理只能查看自己创建的密钥。完整 Secret 不会再次展示。</CardDescription></CardHeader>
      <CardContent className="flex flex-col gap-3">
        {loading?<div className="flex items-center gap-2 py-8 text-sm text-muted-foreground"><LoaderCircle className="size-4 animate-spin"/>正在加载…</div>:keys.length===0?<div className="rounded-md border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">暂无 API Key</div>:keys.map(key=>{
          const perms=parseArray(key.permissions),ips=parseArray(key.ip_allowlist);
          return <div key={key.key_id} className="flex flex-col gap-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><span className="font-medium">{key.name}</span><Badge variant={key.status==="ACTIVE"?"default":"secondary"}>{key.status==="ACTIVE"?"有效":key.status==="REVOKED"?"已撤销":"已过期"}</Badge></div><p className="mt-1 break-all font-mono text-xs text-muted-foreground">{key.key_id} · {key.owner_username}</p></div>{key.status==="ACTIVE"&&<Button variant="destructive" size="sm" onClick={()=>void revoke(key.key_id)}><Trash2 className="size-4"/>撤销</Button>}</div>
            <div className="flex flex-wrap gap-1.5">{perms.map(permission=><Badge key={permission} variant="outline">{permissionLabels[permission]||permission}</Badge>)}</div>
            <div className="grid gap-1 text-xs text-muted-foreground md:grid-cols-3"><span>IP：{ips.length?ips.join(", "):"不限制"}</span><span>过期：{dateText(key.expires_at)}</span><span>最近使用：{dateText(key.last_used_at)}</span></div>
          </div>;
        })}
      </CardContent>
    </Card>
  </div>;
}
