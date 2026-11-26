import { useState, type FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { AlertCircle, LoaderCircle, ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

export function LoginPage() {
  const navigate = useNavigate();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setSubmitting(true);

    try {
      const response = await fetch("/api/auth/login", {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ username, password }),
      });
      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.message || "登录失败，请检查账号和密码");
      }

      navigate(result.data.user.must_change_password ? "/change-password" : "/", { replace: true });
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "登录请求失败，请稍后重试");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4 sm:p-8">
      <Card className="w-full max-w-md shadow-sm">
        <CardHeader className="gap-3 p-6 sm:p-8">
          <div className="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <ShieldCheck className="size-5" />
          </div>
          <div className="flex flex-col gap-1.5">
            <CardTitle className="text-2xl">登录 Auth 管理平台</CardTitle>
            <CardDescription>使用管理员或已授权账号登录，安全管理软件授权与代理业务。</CardDescription>
          </div>
        </CardHeader>

        <CardContent className="px-6 pb-6 sm:px-8 sm:pb-8">
          <form className="flex flex-col gap-5" onSubmit={handleSubmit}>
            <div className="flex flex-col gap-2">
              <label htmlFor="username" className="text-sm font-medium">账号</label>
              <Input
                id="username"
                name="username"
                autoComplete="username"
                placeholder="请输入登录账号"
                value={username}
                onChange={(event) => setUsername(event.target.value)}
                required
                maxLength={64}
                disabled={submitting}
              />
            </div>

            <div className="flex flex-col gap-2">
              <label htmlFor="password" className="text-sm font-medium">密码</label>
              <Input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                placeholder="请输入登录密码"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
                maxLength={1024}
                disabled={submitting}
              />
            </div>

            {error ? (
              <div role="alert" className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                <AlertCircle className="mt-0.5 size-4 shrink-0" />
                <p>{error}</p>
              </div>
            ) : null}

            <Button type="submit" className="h-11 w-full" disabled={submitting || !username.trim() || !password}>
              {submitting ? <LoaderCircle className="size-4 animate-spin" /> : null}
              {submitting ? "正在登录…" : "登录"}
            </Button>

            <p className="text-center text-xs leading-5 text-muted-foreground">
              登录会话使用 HttpOnly Cookie 保存；不会将会话密钥写入浏览器本地存储。
            </p>
          </form>
        </CardContent>
      </Card>
    </main>
  );
}
