import { Activity, AppWindow, ArrowRight, ArrowUpRight, CreditCard, KeyRound, ShieldCheck, Users, Wallet } from "lucide-react";
import { Link } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const metrics = [
  { title: "软件产品", value: "—", note: "已接入的软件数量", icon: AppWindow, tint: "bg-muted text-foreground" },
  { title: "有效授权", value: "—", note: "当前有效授权总量", icon: KeyRound, tint: "bg-muted text-foreground" },
  { title: "平台用户", value: "—", note: "用户与代理账户", icon: Users, tint: "bg-muted text-foreground" },
  { title: "账户余额", value: "—", note: "按平台统一显示单位", icon: Wallet, tint: "bg-muted text-foreground" },
];

const shortcuts = [
  { title: "软件与套餐", desc: "管理软件身份、套餐规则和签名公钥", to: "/apps", icon: AppWindow },
  { title: "授权管理", desc: "签发、查询和维护软件授权", to: "/licenses", icon: KeyRound },
  { title: "用户与代理", desc: "管理账户、角色和代理关系", to: "/users", icon: Users },
  { title: "支付渠道", desc: "配置支付适配器与收款方式", to: "/payments", icon: CreditCard },
];

export function DashboardPage() {
  return (
    <section className="flex flex-col gap-7">
      <div className="relative overflow-hidden rounded-2xl bg-slate-950 px-6 py-8 text-white shadow-xl shadow-slate-900/10 sm:px-8">
        <div className="absolute -right-16 -top-28 size-80 rounded-full bg-indigo-500/20 blur-3xl"/>
        <div className="absolute bottom-0 right-1/4 size-44 rounded-full bg-cyan-400/10 blur-3xl"/>
        <div className="relative flex flex-col justify-between gap-6 md:flex-row md:items-end">
          <div className="max-w-2xl"><Badge className="border border-white/15 bg-white/10 text-indigo-100 hover:bg-white/10">AUTH CONTROL CENTER</Badge><h1 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">欢迎回来，管理工作从这里开始。</h1><p className="mt-3 text-sm leading-6 text-slate-300">集中掌握软件授权、用户代理、资金订单与安全运行情况。选择一个业务入口，继续处理平台事务。</p></div>
          <div className="flex flex-wrap gap-2"><Button asChild className="bg-white text-slate-950 hover:bg-slate-100"><Link to="/apps">管理软件 <ArrowUpRight className="ml-1 size-4"/></Link></Button><Button asChild variant="outline" className="border-white/20 bg-white/5 text-white hover:bg-white/10 hover:text-white"><Link to="/licenses">查看授权</Link></Button></div>
        </div>
      </div>
      <div className="flex flex-col gap-1"><div className="flex items-center justify-between gap-3"><h2 className="text-lg font-semibold tracking-tight">平台概况</h2><span className="text-xs text-muted-foreground">统计数据将在 API 汇总接口接入后显示</span></div><div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{metrics.map(({title,value,note,icon:Icon,tint})=><Card key={title} className="rounded-xl border-border/80 shadow-sm"><CardContent className="flex items-start justify-between p-5"><div><p className="text-sm font-medium text-muted-foreground">{title}</p><p className="mt-3 text-3xl font-semibold tracking-tight">{value}</p><p className="mt-2 text-xs text-muted-foreground">{note}</p></div><span className={`flex size-10 items-center justify-center rounded-xl ${tint}`}><Icon className="size-5"/></span></CardContent></Card>)}</div></div>
      <div className="grid gap-5 xl:grid-cols-[1.45fr_1fr]">
        <Card className="rounded-xl shadow-sm"><CardHeader className="flex flex-row items-center justify-between space-y-0"><div><CardTitle className="text-base">常用业务入口</CardTitle><CardDescription className="mt-1">按日常运营流程快速进入管理模块</CardDescription></div><Badge variant="secondary">快捷操作</Badge></CardHeader><CardContent className="grid gap-3 sm:grid-cols-2">{shortcuts.map(({title,desc,to,icon:Icon})=><Link key={to} to={to} className="group flex items-start gap-3 rounded-xl border border-border/80 p-4 transition hover:border-primary/30 hover:bg-muted/60"><span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground transition group-hover:bg-primary group-hover:text-primary-foreground"><Icon className="size-5"/></span><span className="min-w-0 flex-1"><span className="flex items-center justify-between gap-2 text-sm font-semibold">{title}<ArrowUpRight className="size-4 text-muted-foreground transition group-hover:text-foreground"/></span><span className="mt-1 block text-xs leading-5 text-muted-foreground">{desc}</span></span></Link>)}</CardContent></Card>
        <Card className="rounded-xl shadow-sm"><CardHeader><CardTitle className="text-base">平台服务状态</CardTitle><CardDescription>服务健康信息与安全能力</CardDescription></CardHeader><CardContent className="space-y-4"><div className="flex items-center justify-between gap-3"><div className="flex items-center gap-3"><span className="flex size-9 items-center justify-center rounded-lg bg-muted text-foreground"><ShieldCheck className="size-4"/></span><div><p className="text-sm font-medium">授权安全机制</p><p className="text-xs text-muted-foreground">RSA-PSS 请求签名规范</p></div></div><Badge variant="secondary" className="border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">已集成</Badge></div><div className="flex items-center justify-between gap-3"><div className="flex items-center gap-3"><span className="flex size-9 items-center justify-center rounded-lg bg-muted text-foreground"><Activity className="size-4"/></span><div><p className="text-sm font-medium">区域服务监控</p><p className="text-xs text-muted-foreground">节点运行数据待接入</p></div></div><Badge variant="secondary" className="border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">待监控接入</Badge></div><div className="flex items-center justify-between gap-3"><div className="flex items-center gap-3"><span className="flex size-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><CreditCard className="size-4"/></span><div><p className="text-sm font-medium">支付渠道</p><p className="text-xs text-muted-foreground">真实商户联调状态请到渠道页核验</p></div></div><Badge variant="secondary">需验收</Badge></div><div className="border-t pt-3"><Link to="/monitoring" className="inline-flex items-center gap-2 text-sm font-medium text-foreground hover:text-foreground">查看系统监控 <ArrowRight className="size-4"/></Link></div></CardContent></Card>
      </div>
      <div className="flex flex-col gap-1"><h2 className="text-lg font-semibold tracking-tight">管理建议</h2><p className="text-sm text-muted-foreground">首次使用建议按以下顺序完成平台初始化。</p><div className="grid gap-3 md:grid-cols-3"><div className="rounded-xl border bg-card p-5"><span className="font-mono text-xs font-semibold text-foreground">01 / CONFIGURE</span><h3 className="mt-3 text-sm font-semibold">创建软件与套餐</h3><p className="mt-2 text-xs leading-5 text-muted-foreground">先建立软件产品，再设置授权时长、价格与设备规则。</p><Link to="/apps" className="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-foreground">前往软件管理 <ArrowRight className="size-3.5"/></Link></div><div className="rounded-xl border bg-card p-5"><span className="font-mono text-xs font-semibold text-foreground">02 / OPERATE</span><h3 className="mt-3 text-sm font-semibold">配置用户与授权</h3><p className="mt-2 text-xs leading-5 text-muted-foreground">创建运营账户、分配代理额度并开始签发授权。</p><Link to="/users" className="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-foreground">前往用户管理 <ArrowRight className="size-3.5"/></Link></div><div className="rounded-xl border bg-card p-5"><span className="font-mono text-xs font-semibold text-foreground">03 / SECURE</span><h3 className="mt-3 text-sm font-semibold">完善 API 与安全设置</h3><p className="mt-2 text-xs leading-5 text-muted-foreground">配置密钥、检查审计日志，并使用 SDK 完成程序接入。</p><Link to="/api-keys" className="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-foreground">前往 API 管理 <ArrowRight className="size-3.5"/></Link></div></div></div>
    </section>
  );
}