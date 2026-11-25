import { Activity, AppWindow, KeyRound, Users } from "lucide-react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const metrics = [
  { title: "软件总数", value: "—", detail: "等待接入业务数据", icon: AppWindow },
  { title: "授权总数", value: "—", detail: "等待接入业务数据", icon: KeyRound },
  { title: "用户与代理", value: "—", detail: "等待接入业务数据", icon: Users },
  { title: "区域服务状态", value: "未连接", detail: "国内 / 海外", icon: Activity },
];

export function DashboardPage() {
  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium text-primary">工作台 / 总览</p>
        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">平台总览</h1>
        <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
          统一查看软件授权、用户代理和全球区域服务状态。当前为基础工程阶段，业务统计将在对应模块接入后展示。
        </p>
      </div>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {metrics.map(({ title, value, detail, icon: Icon }) => (
          <Card key={title}>
            <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
              <CardTitle className="text-sm font-medium">{title}</CardTitle>
              <Icon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
              <p className="text-2xl font-semibold tracking-tight">{value}</p>
              <p className="text-xs text-muted-foreground">{detail}</p>
            </CardContent>
          </Card>
        ))}
      </div>
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <Card className="xl:col-span-2">
          <CardHeader>
            <CardTitle>系统介绍</CardTitle>
            <CardDescription>Auth 多软件授权管理平台</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 text-sm leading-6 text-muted-foreground sm:grid-cols-2">
            <div className="rounded-lg bg-muted/60 p-4">
              <p className="font-medium text-foreground">多软件统一管理</p>
              <p className="mt-2">集中维护软件身份、套餐、授权码、设备绑定和授权生命周期。</p>
            </div>
            <div className="rounded-lg bg-muted/60 p-4">
              <p className="font-medium text-foreground">代理与额度体系</p>
              <p className="mt-2">按代理层级管理用户、授权额度、独立余额和财务流水。</p>
            </div>
            <div className="rounded-lg bg-muted/60 p-4">
              <p className="font-medium text-foreground">全球双区域</p>
              <p className="mt-2">国内和海外独立部署，通过事件同步保持授权状态一致。</p>
            </div>
            <div className="rounded-lg bg-muted/60 p-4">
              <p className="font-medium text-foreground">签名与安全</p>
              <p className="mt-2">基于非对称签名、设备绑定、密钥轮换和审计记录保护授权。</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>开发状态</CardTitle>
            <CardDescription>基础工程初始化</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-3 text-sm">
            <div className="flex items-center justify-between gap-3"><span>前端工程</span><span className="text-muted-foreground">骨架已建立</span></div>
            <div className="flex items-center justify-between gap-3"><span>后端 API</span><span className="text-muted-foreground">健康检查骨架</span></div>
            <div className="flex items-center justify-between gap-3"><span>支付渠道</span><span className="text-muted-foreground">适配设计阶段</span></div>
            <div className="flex items-center justify-between gap-3"><span>授权核心</span><span className="text-muted-foreground">待开发</span></div>
          </CardContent>
        </Card>
      </div>
    </section>
  );
}