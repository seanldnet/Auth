import { useEffect, useMemo, useState } from "react";
import { NavLink, Outlet, useNavigate } from "react-router-dom";
import {
  Activity, AppWindow, Bell, BookOpen, CreditCard, FileClock, Globe2, KeyRound, LayoutDashboard,
  Layers, LogOut, Menu, Settings, ShieldCheck, Users, LoaderCircle, Wallet, ReceiptText, X,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type Role = "ADMIN" | "AGENT" | "SUB_AGENT" | "USER";
type SessionUser = { username: string; role: Role; must_change_password?: boolean };
type NavItem = { label: string; to: string; icon: typeof LayoutDashboard; roles: Role[] };
const allRoles: Role[] = ["ADMIN", "AGENT", "SUB_AGENT", "USER"];
const adminRoles: Role[] = ["ADMIN"];
const agentRoles: Role[] = ["AGENT", "SUB_AGENT"];
const groups: { title: string; items: NavItem[] }[] = [
  { title: "工作台", items: [
    { label: "总览", to: "/dashboard", icon: LayoutDashboard, roles: allRoles },
    { label: "通知中心", to: "/notifications", icon: Bell, roles: allRoles },
  ]},
  { title: "授权业务", items: [
    { label: "软件管理", to: "/apps", icon: AppWindow, roles: adminRoles },
    { label: "授权管理", to: "/licenses", icon: KeyRound, roles: adminRoles },
    { label: "我的授权", to: "/agent/licenses", icon: KeyRound, roles: agentRoles },
    { label: "额度分配", to: "/quotas", icon: Layers, roles: adminRoles },
    { label: "我的额度", to: "/agent/quotas", icon: Layers, roles: agentRoles },
  ]},
  { title: "用户与代理", items: [
    { label: "用户管理", to: "/users", icon: Users, roles: ["ADMIN", "AGENT", "SUB_AGENT"] },
    { label: "账号信息", to: "/account", icon: Users, roles: ["USER"] },
  ]},
  { title: "财务中心", items: [
    { label: "我的钱包", to: "/wallet", icon: Wallet, roles: allRoles },
    { label: "账户充值", to: "/top-up", icon: Wallet, roles: allRoles },
    { label: "钱包管理", to: "/wallet-management", icon: Wallet, roles: adminRoles },
    { label: "充值记录", to: "/payment-orders", icon: ReceiptText, roles: allRoles },
    { label: "支付渠道", to: "/payments", icon: CreditCard, roles: adminRoles },
  ]},
  { title: "安全中心", items: [
    { label: "签名密钥", to: "/security-keys", icon: ShieldCheck, roles: adminRoles },
    { label: "操作审计", to: "/audit-logs", icon: FileClock, roles: adminRoles },
    { label: "API Key", to: "/api-keys", icon: KeyRound, roles: ["ADMIN", "AGENT", "SUB_AGENT"] },
  ]},
  { title: "开发者服务", items: [
    { label: "开发者中心", to: "/developer-center", icon: BookOpen, roles: allRoles },
  ]},
  { title: "系统管理", items: [
    { label: "区域节点", to: "/regions", icon: Globe2, roles: adminRoles },
    { label: "系统监控", to: "/monitoring", icon: Activity, roles: adminRoles },
    { label: "系统设置", to: "/settings", icon: Settings, roles: adminRoles },
  ]},
];

export function AdminLayout() {
  const navigate = useNavigate();
  const [user, setUser] = useState<SessionUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [mobileOpen, setMobileOpen] = useState(false);
  const visibleGroups = useMemo(
    () => groups.map(group => ({ ...group, items: group.items.filter(item => user && item.roles.includes(user.role)) })).filter(group => group.items.length > 0),
    [user],
  );

  useEffect(() => {
    const controller = new AbortController();
    fetch("/api/auth/me", { credentials: "include", headers: { Accept: "application/json" }, signal: controller.signal })
      .then(async response => {
        const result = await response.json();
        if (!response.ok || !result.success) { navigate("/login", { replace: true }); return; }
        const sessionUser = result.data.user as SessionUser;
        if (sessionUser.must_change_password) { navigate("/change-password", { replace: true }); return; }
        setUser(sessionUser);
      })
      .catch(() => { if (!controller.signal.aborted) navigate("/login", { replace: true }); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [navigate]);

  async function logout() {
    try {
      await fetch("/api/auth/logout", { method: "POST", credentials: "include", headers: { Accept: "application/json" } });
    } finally { navigate("/login", { replace: true }); }
  }

  if (loading || !user) return <main className="flex min-h-screen items-center justify-center bg-muted/40"><div className="flex items-center gap-3 text-sm text-muted-foreground"><LoaderCircle className="size-5 animate-spin"/>正在验证登录状态…</div></main>;

  const sidebar = (mobile = false) => (
    <div className="flex h-full flex-col bg-slate-950 text-white">
      <div className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-white/10 px-5">
        <div className="flex items-center gap-3"><div className="flex size-9 items-center justify-center rounded-lg bg-indigo-500 font-bold text-white">A</div><div><p className="font-semibold tracking-tight text-white">Auth Console</p><p className="text-xs text-slate-400">授权管理平台</p></div></div>
        {mobile ? <Button variant="ghost" size="icon" aria-label="关闭菜单" onClick={() => setMobileOpen(false)}><X className="size-5"/></Button> : null}
      </div>
      <nav className="flex-1 space-y-5 overflow-y-auto p-4">
        {visibleGroups.map(group => <section key={group.title} className="space-y-1"><p className="px-3 pb-1 text-[11px] font-semibold tracking-[.16em] text-slate-500">{group.title}</p>{group.items.map(({ label, to, icon: Icon }) => <NavLink key={to} to={to} onClick={() => setMobileOpen(false)} className={({ isActive }) => cn("flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors", isActive ? "bg-indigo-500/15 text-indigo-200 ring-1 ring-inset ring-indigo-400/20" : "text-slate-400 hover:bg-white/5 hover:text-white")}><Icon className="size-4 shrink-0"/>{label}</NavLink>)}</section>)}
      </nav>
      <div className="border-t border-white/10 p-4"><p className="text-xs text-slate-500">V1.3 · {user.role} · 管理控制台</p></div>
    </div>
  );

  return <div className="min-h-screen bg-slate-50 text-slate-950">
    <aside className="fixed inset-y-0 left-0 z-20 hidden w-64 border-r lg:block">{sidebar()}</aside>
    {mobileOpen ? <div className="fixed inset-0 z-40 lg:hidden"><Button type="button" variant="ghost" aria-label="关闭菜单遮罩" className="absolute inset-0 h-full w-full rounded-none bg-black/40 hover:bg-black/50" onClick={() => setMobileOpen(false)} /><aside className="absolute inset-y-0 left-0 w-[min(84vw,320px)] shadow-xl">{sidebar(true)}</aside></div> : null}
    <div className="lg:pl-64">
      <header className="sticky top-0 z-10 flex min-h-16 items-center justify-between gap-3 border-b border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur sm:px-6">
        <div className="flex items-center gap-3"><Button className="lg:hidden" variant="ghost" size="icon" aria-label="打开菜单" onClick={() => setMobileOpen(true)}><Menu className="size-5"/></Button><div><p className="text-sm font-semibold tracking-tight">Auth 管理控制台</p><p className="text-xs text-slate-500">多软件授权 · 统一运营中心</p></div></div>
        <div className="flex items-center gap-2 sm:gap-3"><div className="hidden text-right sm:block"><p className="text-sm font-medium">{user.username}</p><p className="text-xs text-muted-foreground">{user.role}</p></div><Button variant="ghost" size="sm" onClick={logout}><LogOut className="size-4"/>退出登录</Button></div>
      </header>
      <main className="mx-auto flex w-full max-w-[1600px] flex-col gap-7 p-4 sm:p-6 lg:p-8"><Outlet/></main>
    </div>
  </div>;
}
