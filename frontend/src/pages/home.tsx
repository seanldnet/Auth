import { Link } from "react-router-dom";
import { ArrowRight, KeyRound, ShieldCheck, Users, Wallet } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";

const modules = [
 {icon:KeyRound,title:"授权管理",description:"管理软件授权、套餐、设备绑定及授权状态。"},
 {icon:Users,title:"用户与代理",description:"按角色和归属关系管理用户、代理及额度。"},
 {icon:Wallet,title:"钱包与订单",description:"查看账户余额、充值订单及资金变动记录。"},
 {icon:ShieldCheck,title:"安全与审计",description:"维护签名密钥、API Key 与操作审计记录。"},
];

export function HomePage(){
 return <main className="flex min-h-screen items-center justify-center bg-muted/30 p-4 sm:p-8">
  <div className="w-full max-w-4xl space-y-6">
   <header className="flex flex-wrap items-center justify-between gap-4">
    <div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground"><ShieldCheck className="size-5"/></span><div><p className="font-semibold tracking-tight">Auth</p><p className="text-xs text-muted-foreground">软件授权管理平台</p></div></div>
    <Badge variant="outline">统一管理入口</Badge>
   </header>
   <Card>
    <CardHeader className="gap-3 p-6 sm:p-8">
     <CardTitle className="text-2xl sm:text-3xl">Auth 授权管理平台</CardTitle>
     <CardDescription className="max-w-2xl text-sm leading-6">统一管理软件授权、用户与代理、账户资金以及开发者接入。登录后根据账号角色显示对应的管理功能。</CardDescription>
     <div className="flex flex-wrap gap-3 pt-2"><Button asChild><Link to="/login">登录管理平台 <ArrowRight/></Link></Button><Button asChild variant="outline"><Link to="/register">注册账号</Link></Button></div>
    </CardHeader>
    <Separator/>
    <CardContent className="grid gap-3 p-6 sm:grid-cols-2 sm:p-8">
     {modules.map(({icon:Icon,title,description})=><div key={title} className="flex items-start gap-3 rounded-lg border p-4"><span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted"><Icon className="size-4"/></span><div className="min-w-0"><p className="text-sm font-medium">{title}</p><p className="mt-1 text-sm leading-5 text-muted-foreground">{description}</p></div></div>)}
    </CardContent>
   </Card>
   <p className="text-center text-xs text-muted-foreground">Auth · 软件授权与运营管理</p>
  </div>
 </main>;
}
