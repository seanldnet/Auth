import { useEffect, useMemo, useState } from "react";
import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { Activity, AppWindow, Bell, BookOpen, CreditCard, FileClock, Globe2, KeyRound, LayoutDashboard, Layers, LogOut, Settings, ShieldCheck, Users, LoaderCircle, Wallet, ReceiptText } from "lucide-react";
import { Button } from "@/components/ui/button";
import { SidebarProvider, Sidebar, SidebarHeader, SidebarContent, SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuItem, SidebarMenuButton, SidebarFooter, SidebarInset, SidebarTrigger, SidebarRail, SidebarGroupContent } from "@/components/ui/sidebar";

type Role = "ADMIN" | "AGENT" | "SUB_AGENT" | "USER";
type SessionUser = { username: string; role: Role; must_change_password?: boolean };
type NavItem = { label: string; to: string; icon: typeof LayoutDashboard; roles: Role[] };
const allRoles: Role[] = ["ADMIN", "AGENT", "SUB_AGENT", "USER"];
const adminRoles: Role[] = ["ADMIN"];
const agentRoles: Role[] = ["AGENT", "SUB_AGENT"];
const groups: { title: string; items: NavItem[] }[] = [
 {title:"工作台",items:[{label:"总览",to:"/dashboard",icon:LayoutDashboard,roles:allRoles},{label:"通知中心",to:"/notifications",icon:Bell,roles:allRoles}]},
 {title:"授权业务",items:[{label:"软件管理",to:"/apps",icon:AppWindow,roles:adminRoles},{label:"授权管理",to:"/licenses",icon:KeyRound,roles:adminRoles},{label:"我的授权",to:"/agent/licenses",icon:KeyRound,roles:agentRoles},{label:"额度分配",to:"/quotas",icon:Layers,roles:adminRoles},{label:"我的额度",to:"/agent/quotas",icon:Layers,roles:agentRoles}]},
 {title:"用户与代理",items:[{label:"用户管理",to:"/users",icon:Users,roles:["ADMIN","AGENT","SUB_AGENT"]},{label:"账号信息",to:"/account",icon:Users,roles:["USER"]}]},
 {title:"财务中心",items:[{label:"我的钱包",to:"/wallet",icon:Wallet,roles:allRoles},{label:"账户充值",to:"/top-up",icon:Wallet,roles:allRoles},{label:"钱包管理",to:"/wallet-management",icon:Wallet,roles:adminRoles},{label:"充值记录",to:"/payment-orders",icon:ReceiptText,roles:allRoles},{label:"支付渠道",to:"/payments",icon:CreditCard,roles:adminRoles}]},
 {title:"安全中心",items:[{label:"签名密钥",to:"/security-keys",icon:ShieldCheck,roles:adminRoles},{label:"操作审计",to:"/audit-logs",icon:FileClock,roles:adminRoles},{label:"API Key",to:"/api-keys",icon:KeyRound,roles:["ADMIN","AGENT","SUB_AGENT"]}]},
 {title:"开发者服务",items:[{label:"开发者中心",to:"/developer-center",icon:BookOpen,roles:allRoles}]},
 {title:"系统管理",items:[{label:"区域节点",to:"/regions",icon:Globe2,roles:adminRoles},{label:"系统监控",to:"/monitoring",icon:Activity,roles:adminRoles},{label:"系统设置",to:"/settings",icon:Settings,roles:adminRoles}]},
];
export function AdminLayout(){
 const navigate=useNavigate();const location=useLocation();const [user,setUser]=useState<SessionUser|null>(null);const [loading,setLoading]=useState(true);
 const visibleGroups=useMemo(()=>groups.map(group=>({...group,items:group.items.filter(item=>user&&item.roles.includes(user.role))})).filter(group=>group.items.length>0),[user]);
 useEffect(()=>{const controller=new AbortController();fetch("/api/auth/me",{credentials:"include",headers:{Accept:"application/json"},signal:controller.signal}).then(async response=>{const result=await response.json();if(!response.ok||!result.success){navigate("/login",{replace:true});return;}const sessionUser=result.data.user as SessionUser;if(sessionUser.must_change_password){navigate("/change-password",{replace:true});return;}setUser(sessionUser);}).catch(()=>{if(!controller.signal.aborted)navigate("/login",{replace:true});}).finally(()=>{if(!controller.signal.aborted)setLoading(false);});return()=>controller.abort();},[navigate]);
 async function logout(){try{await fetch("/api/auth/logout",{method:"POST",credentials:"include",headers:{Accept:"application/json"}});}finally{navigate("/login",{replace:true});}}
 if(loading||!user)return <main className="flex min-h-screen items-center justify-center bg-background"><div className="flex items-center gap-3 text-sm text-muted-foreground"><LoaderCircle className="size-5 animate-spin"/>正在验证登录状态…</div></main>;
 return <SidebarProvider className="bg-background text-foreground">
  <Sidebar collapsible="icon">
   <SidebarHeader><div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">A</div><div className="min-w-0"><p className="truncate text-sm font-semibold">Auth Console</p><p className="truncate text-xs text-muted-foreground">授权管理平台</p></div></SidebarHeader>
   <SidebarContent>{visibleGroups.map(group=><SidebarGroup key={group.title}><SidebarGroupLabel>{group.title}</SidebarGroupLabel><SidebarGroupContent><SidebarMenu>{group.items.map(({label,to,icon:Icon})=><SidebarMenuItem key={to}><SidebarMenuButton asChild isActive={location.pathname===to} tooltip={label}><NavLink to={to}><Icon className="size-4 shrink-0"/><span>{label}</span></NavLink></SidebarMenuButton></SidebarMenuItem>)}</SidebarMenu></SidebarGroupContent></SidebarGroup>)}</SidebarContent>
   <SidebarFooter>V1.3 · {user.role} · 管理控制台</SidebarFooter>
   <SidebarRail />
  </Sidebar>
  <SidebarInset>
   <header className="sticky top-0 z-20 flex min-h-16 items-center justify-between gap-3 border-b bg-background/95 px-4 py-3 backdrop-blur sm:px-6">
    <div className="flex items-center gap-3"><SidebarTrigger/><div><p className="text-sm font-semibold">Auth 管理控制台</p><p className="text-xs text-muted-foreground">多软件授权 · 统一运营中心</p></div></div>
    <div className="flex items-center gap-2 sm:gap-3"><div className="hidden text-right sm:block"><p className="text-sm font-medium">{user.username}</p><p className="text-xs text-muted-foreground">{user.role}</p></div><Button variant="ghost" size="sm" onClick={()=>void logout()}><LogOut className="size-4"/>退出登录</Button></div>
   </header>
   <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-7 p-4 sm:p-6 lg:p-8"><Outlet/></div>
  </SidebarInset>
 </SidebarProvider>;
}
