import { useEffect, useState } from "react";
import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { Activity, AppWindow, CreditCard, KeyRound, LayoutDashboard, LogOut, Settings, Users, LoaderCircle, Wallet, ReceiptText } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const navigation = [
  { label: "总览", to: "/", icon: LayoutDashboard },
  { label: "软件管理", to: "/apps", icon: AppWindow },
  { label: "授权管理", to: "/licenses", icon: KeyRound },
  { label: "用户与代理", to: "/users", icon: Users },
  { label: "账户充值", to: "/top-up", icon: Wallet },
  { label: "充值记录", to: "/payment-orders", icon: ReceiptText },
  { label: "支付渠道", to: "/payments", icon: CreditCard },
  { label: "系统监控", to: "/monitoring", icon: Activity },
  { label: "系统设置", to: "/settings", icon: Settings },
];

type SessionUser = { username: string; role: string; must_change_password?: boolean };

export function AdminLayout() {
  const navigate = useNavigate();
  const [user, setUser] = useState<SessionUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const controller = new AbortController();
    fetch("/api/auth/me", {
      credentials: "include",
      headers: { Accept: "application/json" },
      signal: controller.signal,
    })
      .then(async (response) => {
        const result = await response.json();
        if (!response.ok || !result.success) {
          navigate("/login", { replace: true });
          return;
        }
        const sessionUser = result.data.user as SessionUser;
        if (sessionUser.must_change_password) {
          navigate("/change-password", { replace: true });
          return;
        }
        setUser(sessionUser);
      })
      .catch(() => {
        if (!controller.signal.aborted) navigate("/login", { replace: true });
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [navigate]);

  async function logout() {
    try {
      await fetch("/api/auth/logout", {
        method: "POST",
        credentials: "include",
        headers: { Accept: "application/json" },
      });
    } finally {
      navigate("/login", { replace: true });
    }
  }

  if (loading || !user) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-muted/40">
        <div className="flex items-center gap-3 text-sm text-muted-foreground">
          <LoaderCircle className="size-5 animate-spin" />
          正在验证登录状态…
        </div>
      </main>
    );
  }

  return (
    <div className="min-h-screen bg-muted/40 text-foreground">
      <aside className="fixed inset-y-0 left-0 z-20 hidden w-64 border-r bg-card lg:flex lg:flex-col">
        <div className="flex h-16 items-center gap-3 border-b px-6">
          <div className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold">A</div>
          <div className="min-w-0">
            <p className="font-semibold tracking-tight">Auth Console</p>
            <p className="text-xs text-muted-foreground">授权管理平台</p>
          </div>
        </div>
        <nav className="flex-1 space-y-1 p-4">
          {navigation.filter(({ to }) => user.role === "ADMIN" || (to === "/users" && ["AGENT", "SUB_AGENT"].includes(user.role)) || !["/apps", "/licenses", "/users", "/payments", "/settings"].includes(to)).map(({ label, to, icon: Icon }) => (
            <NavLink key={to} to={to} className={({ isActive }) => cn(
              "flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors",
              isActive ? "bg-primary/10 text-primary" : "text-muted-foreground hover:bg-muted hover:text-foreground",
            )}>
              <Icon className="size-4" />
              {label}
            </NavLink>
          ))}
        </nav>
        <div className="border-t p-4">
          <p className="text-xs text-muted-foreground">V1.3 · 管理控制台</p>
        </div>
      </aside>
      <div className="lg:pl-64">
        <header className="sticky top-0 z-10 flex min-h-16 items-center justify-between gap-4 border-b bg-background/90 px-4 py-3 backdrop-blur sm:px-6">
          <div>
            <p className="text-sm font-semibold">多软件授权管理平台</p>
            <p className="text-xs text-muted-foreground">全球区域 · 统一管理</p>
          </div>
          <div className="flex items-center gap-3">
            <div className="hidden text-right sm:block">
              <p className="text-sm font-medium">{user.username}</p>
              <p className="text-xs text-muted-foreground">{user.role}</p>
            </div>
            <Button variant="ghost" size="sm" onClick={logout}>
              <LogOut className="size-4" />
              退出登录
            </Button>
          </div>
        </header>
        <main className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:gap-8 sm:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
