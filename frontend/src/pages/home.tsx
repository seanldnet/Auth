import { ArrowRight, ArrowUpRight, BadgeCheck, Blocks, Check, Code2, Fingerprint, Globe2, KeyRound, Layers3, LockKeyhole, ShieldCheck, Sparkles, Workflow, Zap } from "lucide-react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const features = [
  { icon: KeyRound, title: "完整授权生命周期", text: "从授权签发、设备绑定，到续期、更换、冻结与验证，统一管理每一份授权。" },
  { icon: Fingerprint, title: "可信身份与设备校验", text: "软件身份、设备指纹与请求签名协同工作，降低密钥滥用和非法调用风险。" },
  { icon: Workflow, title: "代理与额度体系", text: "按软件和套餐分配授权额度，清晰管理代理层级、客户关系与操作边界。" },
  { icon: Wallet, title: "统一钱包与支付", text: "单一余额账户，接入多种支付适配器，订单、回调和财务记录集中追踪。" },
  { icon: Globe2, title: "区域化服务架构", text: "面向国内与海外部署场景设计，为后续多区域扩展预留清晰边界。" },
  { icon: Code2, title: "开发者友好 API", text: "提供 API Key、请求签名规范和多语言 SDK，减少软件接入与维护成本。" },
];
const plans = [
  { name: "体验版", price: "免费", suffix: "", desc: "适合个人开发者验证授权流程与接入方式。", items: ["基础软件接入", "授权签发与验证", "基础设备绑定", "开发文档与 SDK"], featured: false, action: "免费开始" },
  { name: "专业版", price: "¥299", suffix: "/月 起", desc: "适合持续运营的软件产品与小型团队。", items: ["包含体验版全部能力", "代理与额度管理", "API Key 与请求签名", "支付与订单管理", "操作审计"], featured: true, action: "进入控制台" },
  { name: "企业版", price: "定制", suffix: "报价", desc: "适合多产品、多团队及区域化部署需求。", items: ["包含专业版全部能力", "多软件与多角色协作", "区域化部署方案", "专属技术支持", "定制集成与服务"], featured: false, action: "联系咨询" },
];
function Wallet(props: { className?: string }) { return <Layers3 className={props.className}/>; }

export function HomePage() {
  return <div className="min-h-screen overflow-hidden bg-[#f8fafc] text-slate-950">
    <header className="sticky top-0 z-40 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl">
      <div className="mx-auto flex h-[76px] max-w-7xl items-center justify-between px-5 lg:px-8">
        <Link to="/" className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-slate-950 text-white shadow-lg shadow-slate-900/15"><ShieldCheck className="size-5"/></span><span><strong className="block text-[17px] tracking-tight">AUTH<span className="text-indigo-600">.</span></strong><span className="block text-[10px] font-semibold tracking-[.2em] text-slate-400">LICENSE PLATFORM</span></span></Link>
        <nav className="hidden items-center gap-9 text-sm font-medium text-slate-600 md:flex"><a href="#features" className="transition hover:text-slate-950">平台能力</a><a href="#workflow" className="transition hover:text-slate-950">工作流程</a><a href="#pricing" className="transition hover:text-slate-950">套餐方案</a><a href="#developers" className="transition hover:text-slate-950">开发者</a></nav>
        <div className="flex items-center gap-2"><Button asChild variant="ghost" className="hidden sm:inline-flex"><Link to="/login">登录</Link></Button><Button asChild className="rounded-lg bg-indigo-600 px-5 shadow-md shadow-indigo-600/20 hover:bg-indigo-700"><Link to="/register">立即开始 <ArrowUpRight className="ml-1 size-4"/></Link></Button></div>
      </div>
    </header>
    <main>
      <section className="relative isolate border-b border-slate-200/70 bg-white">
        <div className="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_75%_15%,rgba(99,102,241,.13),transparent_36%),radial-gradient(ellipse_at_10%_85%,rgba(14,165,233,.08),transparent_30%)]"/>
        <div className="mx-auto grid max-w-7xl items-center gap-16 px-5 pb-24 pt-20 md:grid-cols-[1.08fr_.92fr] md:pb-32 md:pt-28 lg:px-8">
          <div><Badge className="mb-7 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-indigo-700 hover:bg-indigo-50"><Sparkles className="mr-1 size-3.5"/>面向软件产品的授权基础设施</Badge>
            <h1 className="max-w-3xl text-5xl font-semibold leading-[1.12] tracking-[-.055em] text-slate-950 sm:text-6xl lg:text-[68px]">让软件授权管理<br/><span className="bg-gradient-to-r from-indigo-600 via-blue-600 to-cyan-500 bg-clip-text text-transparent">更安全，也更简单。</span></h1>
            <p className="mt-7 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">Auth 为多软件产品提供统一的授权签发、设备验证、代理分销、支付结算与开发者 API。把复杂的授权基础设施交给平台，让团队专注产品本身。</p>
            <div className="mt-9 flex flex-wrap gap-3"><Button asChild size="lg" className="h-12 rounded-xl bg-indigo-600 px-6 text-base shadow-lg shadow-indigo-600/20 hover:bg-indigo-700"><Link to="/register">创建账户 <ArrowRight className="ml-2 size-4"/></Link></Button><Button asChild size="lg" variant="outline" className="h-12 rounded-xl border-slate-300 bg-white px-6 text-base"><a href="#features">了解平台能力</a></Button></div>
            <div className="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3 text-xs font-medium text-slate-500"><span className="flex items-center gap-2"><Check className="size-4 text-emerald-600"/>多软件统一管理</span><span className="flex items-center gap-2"><Check className="size-4 text-emerald-600"/>API 与 SDK 接入</span><span className="flex items-center gap-2"><Check className="size-4 text-emerald-600"/>代理权限隔离</span></div>
          </div>
          <div className="relative mx-auto w-full max-w-[540px]">
            <div className="absolute -inset-7 rounded-[36px] bg-gradient-to-br from-indigo-200/60 via-sky-100/30 to-cyan-100/70 blur-2xl"/>
            <div className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_30px_90px_-35px_rgba(15,23,42,.35)]">
              <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div className="flex items-center gap-2"><span className="size-2 rounded-full bg-emerald-500"/><span className="text-xs font-semibold text-slate-700">AUTH CONTROL CENTER</span></div><span className="text-[10px] tracking-wider text-slate-400">OVERVIEW / LIVE</span></div>
              <div className="grid grid-cols-2 gap-3 p-5"><div className="rounded-xl bg-slate-950 p-5 text-white"><p className="text-xs text-slate-400">授权服务</p><p className="mt-3 text-3xl font-semibold">Secure</p><p className="mt-2 text-[11px] text-emerald-300">签名验证 · 设备校验</p></div><div className="rounded-xl bg-indigo-50 p-5"><p className="text-xs text-indigo-600">产品管理</p><p className="mt-3 text-3xl font-semibold text-slate-900">Multi-app</p><p className="mt-2 text-[11px] text-slate-500">多软件统一接入</p></div>
                <div className="col-span-2 rounded-xl border border-slate-100 p-5"><div className="flex items-center justify-between"><p className="text-sm font-semibold">授权生命周期</p><Badge variant="secondary">统一工作流</Badge></div><div className="mt-5 grid grid-cols-4 gap-2 text-center">{["签发","验证","续期","审计"].map((x,i)=><div key={x}><div className="mx-auto flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">{[KeyRound,ShieldCheck,Zap,BadgeCheck].map((I,j)=>i===j?<I key={j} className="size-5"/>:null)}</div><p className="mt-2 text-xs font-medium text-slate-600">{x}</p></div>)}</div></div>
                <div className="col-span-2 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span className="text-xs text-slate-500">API 请求安全</span><span className="flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><LockKeyhole className="size-3.5"/>签名与防重放机制</span></div>
              </div>
            </div>
            <div className="absolute -bottom-6 -left-7 hidden items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-xl sm:flex"><span className="flex size-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><ShieldCheck className="size-5"/></span><span><strong className="block text-xs">安全优先</strong><span className="text-[10px] text-slate-500">从接口到设备验证</span></span></div>
          </div>
        </div>
      </section>
      <section id="features" className="mx-auto max-w-7xl px-5 py-24 lg:px-8"><div className="max-w-2xl"><p className="text-xs font-bold tracking-[.2em] text-indigo-600">PLATFORM CAPABILITIES</p><h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">授权业务所需能力，一站式构建</h2><p className="mt-4 leading-7 text-slate-600">从软件接入到终端用户管理，从代理分销到支付结算，核心流程在同一个平台内完成。</p></div>
        <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{features.map(({icon:Icon,title,text})=><Card key={title} className="group rounded-2xl border-slate-200/80 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-950/5"><CardHeader className="p-6 pb-3"><span className="mb-4 flex size-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition group-hover:bg-indigo-600 group-hover:text-white"><Icon className="size-5"/></span><CardTitle className="text-lg">{title}</CardTitle><CardDescription className="pt-1 leading-6 text-slate-500">{text}</CardDescription></CardHeader></Card>)}</div>
      </section>
      <section id="workflow" className="border-y border-slate-200 bg-white"><div className="mx-auto grid max-w-7xl gap-14 px-5 py-24 md:grid-cols-2 md:items-center lg:px-8"><div><p className="text-xs font-bold tracking-[.2em] text-indigo-600">HOW IT WORKS</p><h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">从接入到运营，流程清晰可控</h2><p className="mt-5 leading-7 text-slate-600">平台将软件、套餐、授权、用户、代理和支付数据关联起来，让每一步操作都有记录、有权限边界、可追踪。</p><Button asChild className="mt-8 rounded-lg bg-slate-950 hover:bg-slate-800"><Link to="/developer-center">查看开发者文档 <ArrowUpRight className="ml-2 size-4"/></Link></Button></div>
        <div className="space-y-3">{[{n:"01",t:"创建软件产品",d:"建立软件身份与安全配置"},{n:"02",t:"配置套餐与授权规则",d:"定义授权时长、设备限制与价格"},{n:"03",t:"接入 API / SDK",d:"通过签名请求完成授权验证"},{n:"04",t:"开展用户与代理运营",d:"签发授权、管理额度并跟踪订单"}].map(x=><div key={x.n} className="flex gap-5 rounded-xl border border-slate-200 bg-slate-50/70 p-5"><span className="font-mono text-sm font-semibold text-indigo-600">{x.n}</span><div><h3 className="font-semibold">{x.t}</h3><p className="mt-1 text-sm text-slate-500">{x.d}</p></div></div>)}</div>
      </div></section>
      <section id="pricing" className="mx-auto max-w-7xl px-5 py-24 lg:px-8"><div className="mx-auto max-w-2xl text-center"><p className="text-xs font-bold tracking-[.2em] text-indigo-600">SIMPLE & FLEXIBLE</p><h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">选择适合业务阶段的方案</h2><p className="mt-4 leading-7 text-slate-600">从产品验证到规模化运营，按团队需求选择服务方案。以下为展示方案，实际软件套餐与售价以平台发布配置为准。</p></div>
        <div className="mt-12 grid items-stretch gap-5 lg:grid-cols-3">{plans.map(plan=><Card key={plan.name} className={`relative flex flex-col rounded-2xl ${plan.featured?"border-indigo-500 shadow-xl shadow-indigo-950/10 ring-1 ring-indigo-500":"border-slate-200 shadow-sm"}`}><CardHeader className="p-7 pb-4"><div className="flex items-center justify-between"><CardTitle className="text-xl">{plan.name}</CardTitle>{plan.featured?<Badge className="bg-indigo-600">推荐方案</Badge>:null}</div><CardDescription className="min-h-12 pt-2 leading-6">{plan.desc}</CardDescription><div className="mt-5 flex items-baseline gap-1"><span className="text-4xl font-semibold tracking-tight">{plan.price}</span><span className="text-sm text-slate-500">{plan.suffix}</span></div></CardHeader><CardContent className="flex flex-1 flex-col px-7 pb-7"><div className="mb-6 h-px bg-slate-100"/><ul className="flex-1 space-y-3">{plan.items.map(item=><li key={item} className="flex items-center gap-2 text-sm text-slate-600"><Check className="size-4 shrink-0 text-emerald-600"/>{item}</li>)}</ul><Button asChild className={`mt-8 h-11 rounded-lg ${plan.featured?"bg-indigo-600 hover:bg-indigo-700":"bg-slate-950 hover:bg-slate-800"}`}><Link to={plan.action==="联系咨询"?"/register":"/register"}>{plan.action}<ArrowRight className="ml-2 size-4"/></Link></Button></CardContent></Card>)}</div>
        <p className="mt-6 text-center text-xs text-slate-400">价格为展示用参考方案，不构成正式报价；具体套餐、计费周期及权益以实际发布为准。</p>
      </section>
      <section id="developers" className="bg-slate-950 text-white"><div className="mx-auto flex max-w-7xl flex-col items-start justify-between gap-8 px-5 py-16 md:flex-row md:items-center lg:px-8"><div><p className="text-xs font-bold tracking-[.2em] text-indigo-300">BUILT FOR DEVELOPERS</p><h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">把授权基础设施，交给 Auth。</h2><p className="mt-4 max-w-xl leading-7 text-slate-300">使用标准 API 与多语言 SDK 接入授权服务，安全能力与运营工具随业务一起成长。</p></div><Button asChild size="lg" className="h-12 rounded-xl bg-white px-6 text-slate-950 hover:bg-slate-100"><Link to="/register">开始接入 <ArrowUpRight className="ml-2 size-4"/></Link></Button></div></section>
    </main>
    <footer className="bg-slate-950 text-slate-400"><div className="mx-auto flex max-w-7xl flex-col justify-between gap-5 border-t border-white/10 px-5 py-8 text-xs sm:flex-row lg:px-8"><span>© {new Date().getFullYear()} Auth License Platform. All rights reserved.</span><div className="flex gap-6"><Link to="/login" className="hover:text-white">控制台登录</Link><Link to="/register" className="hover:text-white">创建账户</Link></div></div></footer>
  </div>;
}