import { ArrowRight, ArrowUpRight, BadgeCheck, Check, Code2, Fingerprint, Globe2, KeyRound, Layers3, LockKeyhole, ShieldCheck, Workflow, Zap } from "lucide-react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";

const features = [
  { icon: KeyRound, title: "完整授权生命周期", text: "从授权签发、设备绑定，到续期、更换、冻结与验证，统一管理每一份授权。" },
  { icon: Fingerprint, title: "可信身份与设备校验", text: "软件身份、设备指纹与请求签名协同工作，降低密钥滥用和非法调用风险。" },
  { icon: Workflow, title: "代理与额度体系", text: "按软件和套餐分配授权额度，清晰管理代理层级、客户关系与操作边界。" },
  { icon: Layers3, title: "统一钱包与支付", text: "统一管理账户余额、支付订单与财务记录，关键业务操作可追踪。" },
  { icon: Globe2, title: "区域化服务架构", text: "面向不同部署场景设计，为多区域服务扩展预留清晰边界。" },
  { icon: Code2, title: "开发者 API", text: "提供 API Key、请求签名规范和 SDK 接入能力，降低集成与维护成本。" },
];

const plans = [
  { name: "体验版", price: "免费", suffix: "", desc: "适合个人开发者验证授权流程与接入方式。", items: ["基础软件接入", "授权签发与验证", "基础设备绑定", "开发文档与 SDK"], featured: false },
  { name: "专业版", price: "¥299", suffix: "/月 起", desc: "适合持续运营的软件产品与小型团队。", items: ["包含体验版全部能力", "代理与额度管理", "API Key 与请求签名", "支付与订单管理", "操作审计"], featured: true },
  { name: "企业版", price: "定制", suffix: "报价", desc: "适合多产品、多团队及区域化部署需求。", items: ["包含专业版全部能力", "多软件与多角色协作", "区域化部署方案", "专属技术支持", "定制集成与服务"], featured: false },
];

const workflow = [
  { number: "01", title: "创建软件产品", description: "建立软件身份与安全配置。" },
  { number: "02", title: "配置套餐与授权规则", description: "定义授权时长、设备限制与业务规则。" },
  { number: "03", title: "接入 API / SDK", description: "通过标准签名请求完成授权验证。" },
  { number: "04", title: "开展用户与代理运营", description: "签发授权、管理额度并跟踪订单。" },
];

export function HomePage() {
  return (
    <div className="min-h-screen bg-background text-foreground">
      <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
          <Link to="/" className="flex items-center gap-3">
            <span className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground"><ShieldCheck className="size-5" /></span>
            <span><strong className="block text-base tracking-tight">AUTH</strong><span className="block text-[10px] tracking-[.18em] text-muted-foreground">LICENSE PLATFORM</span></span>
          </Link>
          <nav className="hidden items-center gap-7 text-sm text-muted-foreground md:flex">
            <a href="#features" className="transition-colors hover:text-foreground">平台能力</a>
            <a href="#workflow" className="transition-colors hover:text-foreground">工作流程</a>
            <a href="#pricing" className="transition-colors hover:text-foreground">套餐方案</a>
            <a href="#developers" className="transition-colors hover:text-foreground">开发者</a>
          </nav>
          <div className="flex items-center gap-2">
            <Button asChild variant="ghost" size="sm"><Link to="/login">登录</Link></Button>
            <Button asChild size="sm"><Link to="/register">立即开始 <ArrowUpRight /></Link></Button>
          </div>
        </div>
      </header>

      <main>
        <section className="border-b bg-muted/30">
          <div className="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 md:grid-cols-2 md:py-24 lg:px-8">
            <div>
              <Badge variant="secondary" className="mb-6 gap-1.5"><ShieldCheck className="size-3.5" /> 面向软件产品的授权基础设施</Badge>
              <h1 className="max-w-2xl text-4xl font-semibold leading-tight tracking-tight sm:text-5xl lg:text-6xl">让软件授权管理<br />更安全，也更简单。</h1>
              <p className="mt-6 max-w-xl text-base leading-8 text-muted-foreground sm:text-lg">Auth 为多软件产品提供统一的授权签发、设备验证、代理分销、支付结算与开发者 API。把复杂的授权基础设施交给平台，让团队专注产品本身。</p>
              <div className="mt-8 flex flex-wrap gap-3">
                <Button asChild size="lg"><Link to="/register">创建账户 <ArrowRight /></Link></Button>
                <Button asChild size="lg" variant="outline"><a href="#features">了解平台能力</a></Button>
              </div>
              <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-muted-foreground">
                <span className="flex items-center gap-2"><Check className="size-4" />多软件统一管理</span>
                <span className="flex items-center gap-2"><Check className="size-4" />API 与 SDK 接入</span>
                <span className="flex items-center gap-2"><Check className="size-4" />代理权限隔离</span>
              </div>
            </div>
            <Card className="overflow-hidden">
              <CardHeader className="border-b bg-card">
                <div className="flex items-center justify-between gap-3">
                  <div><CardTitle className="text-base">AUTH CONTROL CENTER</CardTitle><CardDescription className="mt-1">授权管理平台能力概览</CardDescription></div>
                  <Badge variant="outline">平台概览</Badge>
                </div>
              </CardHeader>
              <CardContent className="grid grid-cols-2 gap-3 p-5 sm:p-6">
                <div className="rounded-lg bg-primary p-4 text-primary-foreground">
                  <p className="text-sm opacity-75">授权安全</p><p className="mt-3 text-2xl font-semibold">Secure</p><p className="mt-1 text-xs opacity-75">签名验证 · 设备校验</p>
                </div>
                <div className="rounded-lg bg-muted p-4">
                  <p className="text-sm text-muted-foreground">软件管理</p><p className="mt-3 text-2xl font-semibold">Multi-app</p><p className="mt-1 text-xs text-muted-foreground">多软件统一接入</p>
                </div>
                <div className="col-span-2 rounded-lg border p-4">
                  <div className="flex items-center justify-between gap-2"><p className="text-sm font-medium">授权生命周期</p><Badge variant="secondary">统一工作流</Badge></div>
                  <div className="mt-5 grid grid-cols-4 gap-2 text-center">
                    {[{ icon: KeyRound, label: "签发" }, { icon: ShieldCheck, label: "验证" }, { icon: Zap, label: "续期" }, { icon: BadgeCheck, label: "审计" }].map(({ icon: Icon, label }) => <div key={label}><span className="mx-auto flex size-10 items-center justify-center rounded-lg bg-muted"><Icon className="size-5" /></span><p className="mt-2 text-xs text-muted-foreground">{label}</p></div>)}
                  </div>
                </div>
                <div className="col-span-2 flex items-center justify-between gap-3 rounded-lg border px-4 py-3">
                  <span className="text-sm text-muted-foreground">API 请求安全</span><span className="flex items-center gap-2 text-sm font-medium"><LockKeyhole className="size-4" />签名与防重放机制</span>
                </div>
              </CardContent>
            </Card>
          </div>
        </section>

        <section id="features" className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
          <div className="max-w-2xl"><p className="text-sm font-medium text-muted-foreground">PLATFORM CAPABILITIES</p><h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">授权业务所需能力，一站式构建</h2><p className="mt-4 leading-7 text-muted-foreground">从软件接入到终端用户管理，从代理分销到支付结算，核心流程在同一个平台内完成。</p></div>
          <div className="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {features.map(({ icon: Icon, title, text }) => <Card key={title}><CardHeader><span className="mb-2 flex size-10 items-center justify-center rounded-lg bg-muted"><Icon className="size-5" /></span><CardTitle className="text-base">{title}</CardTitle><CardDescription className="leading-6">{text}</CardDescription></CardHeader></Card>)}
          </div>
        </section>

        <section id="workflow" className="border-y bg-muted/30">
          <div className="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-2 md:items-center lg:px-8 lg:py-20">
            <div><p className="text-sm font-medium text-muted-foreground">HOW IT WORKS</p><h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">从接入到运营，流程清晰可控</h2><p className="mt-5 leading-7 text-muted-foreground">平台将软件、套餐、授权、用户、代理和支付数据关联起来，让每一步操作都有记录、有权限边界、可追踪。</p><Button asChild className="mt-7"><Link to="/developer-center">查看开发者文档 <ArrowUpRight /></Link></Button></div>
            <div className="space-y-3">{workflow.map(step => <Card key={step.number}><CardContent className="flex items-start gap-4 p-4"><span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted text-sm font-semibold tabular-nums">{step.number}</span><div><h3 className="font-medium">{step.title}</h3><p className="mt-1 text-sm text-muted-foreground">{step.description}</p></div></CardContent></Card>)}</div>
          </div>
        </section>

        <section id="pricing" className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
          <div className="mx-auto max-w-2xl text-center"><p className="text-sm font-medium text-muted-foreground">SIMPLE & FLEXIBLE</p><h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">选择适合业务阶段的方案</h2><p className="mt-4 leading-7 text-muted-foreground">从产品验证到规模化运营，按团队需求选择服务方案。以下为展示方案，实际软件套餐与售价以平台发布配置为准。</p></div>
          <div className="mt-10 grid items-stretch gap-4 lg:grid-cols-3">
            {plans.map(plan => <Card key={plan.name} className={plan.featured ? "border-primary shadow-md" : ""}>
              <CardHeader><div className="flex items-center justify-between gap-2"><CardTitle className="text-xl">{plan.name}</CardTitle>{plan.featured ? <Badge>推荐方案</Badge> : null}</div><CardDescription className="min-h-10 leading-6">{plan.desc}</CardDescription><div className="flex items-baseline gap-1 pt-3"><span className="text-3xl font-semibold tracking-tight">{plan.price}</span><span className="text-sm text-muted-foreground">{plan.suffix}</span></div></CardHeader>
              <Separator />
              <CardContent className="flex h-full flex-col pt-5"><ul className="flex-1 space-y-3">{plan.items.map(item => <li key={item} className="flex items-center gap-2 text-sm text-muted-foreground"><Check className="size-4 shrink-0" />{item}</li>)}</ul><Button asChild variant={plan.featured ? "default" : "outline"} className="mt-7 w-full"><Link to="/register">{plan.name === "企业版" ? "联系咨询" : "开始使用"} <ArrowRight /></Link></Button></CardContent>
            </Card>)}
          </div>
          <p className="mt-5 text-center text-xs text-muted-foreground">价格为展示用参考方案，不构成正式报价；具体套餐、计费周期及权益以实际发布为准。</p>
        </section>

        <section id="developers" className="border-y bg-muted/30">
          <div className="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 px-4 py-14 sm:px-6 md:flex-row md:items-center lg:px-8">
            <div><p className="text-sm font-medium text-muted-foreground">BUILT FOR DEVELOPERS</p><h2 className="mt-3 text-3xl font-semibold tracking-tight">把授权基础设施，交给 Auth。</h2><p className="mt-3 max-w-xl leading-7 text-muted-foreground">使用标准 API 与多语言 SDK 接入授权服务，安全能力与运营工具随业务一起成长。</p></div>
            <Button asChild size="lg"><Link to="/register">开始接入 <ArrowUpRight /></Link></Button>
          </div>
        </section>
      </main>

      <footer className="bg-background">
        <div className="mx-auto flex max-w-7xl flex-col justify-between gap-4 px-4 py-7 text-xs text-muted-foreground sm:flex-row sm:px-6 lg:px-8">
          <span>© {new Date().getFullYear()} Auth License Platform. All rights reserved.</span>
          <div className="flex gap-5"><Link to="/login" className="hover:text-foreground">控制台登录</Link><Link to="/register" className="hover:text-foreground">创建账户</Link></div>
        </div>
      </footer>
    </div>
  );
}
