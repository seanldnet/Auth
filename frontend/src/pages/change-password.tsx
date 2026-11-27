import { useEffect, useState, type FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { AlertCircle, KeyRound, LoaderCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

export function ChangePasswordPage() {
  const navigate = useNavigate();
  useEffect(() => {
    const controller = new AbortController();
    fetch("/api/auth/me", { credentials: "include", headers: { Accept: "application/json" }, signal: controller.signal })
      .then(async response => {
        const result = await response.json();
        if (!response.ok || !result.success) { navigate("/login", { replace: true }); return; }
        const user = result.data.user;
        if (user.role !== "ADMIN" || !user.must_change_password) navigate("/dashboard", { replace: true });
      })
      .catch(() => { if (!controller.signal.aborted) navigate("/login", { replace: true }); });
    return () => controller.abort();
  }, [navigate]);

  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");

    if (newPassword.length < 12) {
      setError("新密码至少需要 12 个字符");
      return;
    }
    if (newPassword !== confirmPassword) {
      setError("两次输入的新密码不一致");
      return;
    }

    setSubmitting(true);
    try {
      const response = await fetch("/api/auth/change-password", {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          current_password: currentPassword,
          new_password: newPassword,
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || "密码修改失败");
      }
      navigate("/dashboard", { replace: true });
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "密码修改请求失败");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4 sm:p-8">
      <Card className="w-full max-w-md shadow-sm">
        <CardHeader className="gap-3 p-6 sm:p-8">
          <div className="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <KeyRound className="size-5" />
          </div>
          <CardTitle className="text-2xl">请先修改初始密码</CardTitle>
          <CardDescription>为保护管理员账户，首次登录必须设置至少 12 位的新密码。</CardDescription>
        </CardHeader>
        <CardContent className="px-6 pb-6 sm:px-8 sm:pb-8">
          <form className="flex flex-col gap-5" onSubmit={handleSubmit}>
            <div className="flex flex-col gap-2">
              <label htmlFor="current-password" className="text-sm font-medium">当前密码</label>
              <Input id="current-password" type="password" autoComplete="current-password" value={currentPassword} onChange={(event) => setCurrentPassword(event.target.value)} required disabled={submitting} />
            </div>
            <div className="flex flex-col gap-2">
              <label htmlFor="new-password" className="text-sm font-medium">新密码</label>
              <Input id="new-password" type="password" autoComplete="new-password" value={newPassword} onChange={(event) => setNewPassword(event.target.value)} minLength={12} required disabled={submitting} />
            </div>
            <div className="flex flex-col gap-2">
              <label htmlFor="confirm-password" className="text-sm font-medium">确认新密码</label>
              <Input id="confirm-password" type="password" autoComplete="new-password" value={confirmPassword} onChange={(event) => setConfirmPassword(event.target.value)} minLength={12} required disabled={submitting} />
            </div>
            {error ? (
              <div role="alert" className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                <AlertCircle className="mt-0.5 size-4 shrink-0" />
                <p>{error}</p>
              </div>
            ) : null}
            <Button type="submit" className="h-11 w-full" disabled={submitting || !currentPassword || !newPassword || !confirmPassword}>
              {submitting ? <LoaderCircle className="size-4 animate-spin" /> : null}
              {submitting ? "正在保存…" : "保存新密码"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </main>
  );
}
