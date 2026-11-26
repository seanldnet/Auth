import { useState, type FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import { AlertCircle, LoaderCircle, UserPlus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

export function RegisterPage() {
  const navigate=useNavigate();
  const [username,setUsername]=useState("");
  const [password,setPassword]=useState("");
  const [confirm,setConfirm]=useState("");
  const [inviteCode,setInviteCode]=useState(new URLSearchParams(window.location.search).get("invite")||"");
  const [error,setError]=useState("");
  const [submitting,setSubmitting]=useState(false);
  async function submit(event:FormEvent<HTMLFormElement>){
    event.preventDefault();setError("");
    if(password!==confirm){setError("两次输入的密码不一致。");return;}
    setSubmitting(true);
    try{
      const response=await fetch("/api/auth/register",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json"},body:JSON.stringify({username,password,invite_code:inviteCode})});
      const result=await response.json();
      if(!response.ok||!result.success)throw new Error(result.message||"注册失败");
      navigate("/login?registered=1",{replace:true});
    }catch(cause){setError(cause instanceof Error?cause.message:"注册失败，请稍后重试");}
    finally{setSubmitting(false);}
  }
  return <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4 sm:p-8"><Card className="w-full max-w-md shadow-sm"><CardHeader className="gap-3 p-6 sm:p-8"><div className="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground"><UserPlus className="size-5"/></div><div className="flex flex-col gap-1.5"><CardTitle className="text-2xl">注册 Auth 账号</CardTitle><CardDescription>填写账号信息完成注册。没有邀请码时，账号会进入平台公共用户池。</CardDescription></div></CardHeader><CardContent className="px-6 pb-6 sm:px-8 sm:pb-8"><form onSubmit={submit} className="flex flex-col gap-4">
    <div className="flex flex-col gap-2"><label className="text-sm font-medium">登录账号</label><Input value={username} onChange={e=>setUsername(e.target.value)} minLength={3} maxLength={64} required placeholder="3–64 位字母、数字或 ._-"/></div>
    <div className="flex flex-col gap-2"><label className="text-sm font-medium">登录密码</label><Input type="password" value={password} onChange={e=>setPassword(e.target.value)} minLength={12} maxLength={1024} required autoComplete="new-password" placeholder="至少 12 位"/></div>
    <div className="flex flex-col gap-2"><label className="text-sm font-medium">确认密码</label><Input type="password" value={confirm} onChange={e=>setConfirm(e.target.value)} minLength={12} required autoComplete="new-password" placeholder="再次输入密码"/></div>
    <div className="flex flex-col gap-2"><label className="text-sm font-medium">邀请码（选填）</label><Input value={inviteCode} onChange={e=>setInviteCode(e.target.value)} maxLength={32} placeholder="没有邀请码可留空"/></div>
    {error?<div role="alert" className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"><AlertCircle className="mt-0.5 size-4 shrink-0"/>{error}</div>:null}
    <Button type="submit" className="h-11 w-full" disabled={submitting||!username.trim()||password.length<12||confirm.length<12}>{submitting?<LoaderCircle className="size-4 animate-spin"/>:<UserPlus className="size-4"/>}{submitting?"正在注册…":"创建账号"}</Button>
    <p className="text-center text-sm text-muted-foreground">已有账号？<Link className="font-medium text-primary hover:underline" to="/login">返回登录</Link></p>
  </form></CardContent></Card></main>;
}
