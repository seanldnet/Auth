import { useState, type FormEvent } from "react";
import { LoaderCircle, Send, UserRound } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

export function AccountPage(){
 const [inviteCode,setInviteCode]=useState("");
 const [reason,setReason]=useState("");
 const [saving,setSaving]=useState(false);
 const [error,setError]=useState("");
 const [notice,setNotice]=useState("");
 async function submit(event:FormEvent<HTMLFormElement>){
  event.preventDefault();setSaving(true);setError("");setNotice("");
  try{
   const response=await fetch("/api/auth/transfer-request",{method:"POST",credentials:"include",headers:{"Content-Type":"application/json",Accept:"application/json"},body:JSON.stringify({invite_code:inviteCode.trim(),reason})});
   const result=await response.json();
   if(!response.ok||!result.success)throw new Error(result.message||"提交失败");
   setNotice("归属变更申请已提交，请等待管理员审批。");setInviteCode("");setReason("");
  }catch(cause){setError(cause instanceof Error?cause.message:"提交申请失败");}
  finally{setSaving(false);}
 }
 return <section className="mx-auto flex w-full max-w-3xl flex-col gap-6">
  <div className="flex flex-col gap-2"><p className="text-sm font-medium text-primary">个人中心</p><h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">账号信息</h1><p className="text-sm leading-6 text-muted-foreground">管理账号归属。变更申请由管理员统一审核，审核通过后才会调整代理关系。</p></div>
  <Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><UserRound className="size-4"/>申请变更代理归属</CardTitle><CardDescription>填写新代理的邀请码；留空表示申请转入平台公共用户池。申请通过前，当前代理关系保持不变。</CardDescription></CardHeader><CardContent><form onSubmit={submit} className="flex flex-col gap-4">
   <div className="flex flex-col gap-2"><label className="text-sm font-medium">新代理邀请码（选填）</label><Input value={inviteCode} onChange={e=>setInviteCode(e.target.value)} maxLength={32} placeholder="输入代理或下级代理邀请码；留空转入公共用户池"/></div>
   <div className="flex flex-col gap-2"><label className="text-sm font-medium">申请原因（选填）</label><Input value={reason} onChange={e=>setReason(e.target.value)} maxLength={500} placeholder="请简要说明变更原因（最多 500 字）"/></div>
   {error?<div role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{error}</div>:null}
   {notice?<div className="rounded-md border border-emerald-600/20 bg-emerald-600/5 p-3 text-sm text-emerald-700">{notice}</div>:null}
   <Button type="submit" disabled={saving} className="self-start">{saving?<LoaderCircle className="size-4 animate-spin"/>:<Send className="size-4"/>}{saving?"正在提交…":"提交归属变更申请"}</Button>
  </form></CardContent></Card>
 </section>;
}
