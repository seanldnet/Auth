import { ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export function LoginPage() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4 sm:p-8">
      <Card className="w-full max-w-md">
        <CardHeader className="gap-3 p-6 sm:p-8">
          <div className="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <ShieldCheck className="size-5" />
          </div>
          <div className="flex flex-col gap-1.5">
            <CardTitle className="text-2xl">登录 Auth 管理平台</CardTitle>
            <CardDescription>安全、统一地管理软件授权与代理业务</CardDescription>
          </div>
        </CardHeader>
        <CardContent className="flex flex-col gap-4 px-6 pb-6 sm:px-8 sm:pb-8">
          <p className="text-sm leading-6 text-muted-foreground">
            登录认证接口尚未接入。此页面仅作为前端基础布局，不提供虚假的登录成功状态。
          </p>
          <Button className="w-full" disabled>等待接入认证服务</Button>
        </CardContent>
      </Card>
    </main>
  );
}