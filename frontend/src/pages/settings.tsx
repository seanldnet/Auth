import { useEffect, useState } from "react";
import { Check, LoaderCircle, Settings2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

type Unit = "CNY" | "U";

export function SettingsPage() {
  const [unit, setUnit] = useState<Unit>("CNY");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    fetch("/api/admin/wallet-display-unit", { credentials: "include", headers: { Accept: "application/json" } })
      .then(async (response) => {
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || "读取显示设置失败");
        setUnit(result.data.wallet_display_unit === "U" ? "U" : "CNY");
      })
      .catch((cause) => setError(cause instanceof Error ? cause.message : "读取设置失败"))
      .finally(() => setLoading(false));
  }, []);

  async function save() {
    setSaving(true);
    setError("");
    setSaved(false);
    try {
      const response = await fetch("/api/admin/wallet-display-unit", {
        method: "PUT",
        credentials: "include",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ wallet_display_unit: unit }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "保存显示设置失败");
      setSaved(true);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "保存设置失败");
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium text-primary">系统管理 / 显示设置</p>
        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">系统设置</h1>
        <p className="text-sm text-muted-foreground">这里只设置钱包金额的显示方式，不会改变任何用户的实际余额。</p>
      </div>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base"><Settings2 className="size-4" />钱包金额显示单位</CardTitle>
          <CardDescription>全站统一生效。余额为 100 时，切换单位只改变显示文本。</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {loading ? <div className="flex items-center gap-2 text-sm text-muted-foreground"><LoaderCircle className="size-4 animate-spin" />正在读取设置…</div> : (
            <>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Button type="button" variant={unit === "CNY" ? "default" : "outline"} className="h-14 justify-start" onClick={() => { setUnit("CNY"); setSaved(false); }}>人民币（¥）</Button>
                <Button type="button" variant={unit === "U" ? "default" : "outline"} className="h-14 justify-start" onClick={() => { setUnit("U"); setSaved(false); }}>U</Button>
              </div>
              <div className="rounded-lg border p-4">
                <p className="text-xs text-muted-foreground">余额 100 的显示预览</p>
                <p className="mt-1 text-2xl font-semibold tabular-nums">{unit === "CNY" ? "¥100.00" : "100 U"}</p>
              </div>
              <div className="flex flex-wrap items-center gap-3">
                <Button onClick={save} disabled={saving}>{saving ? <LoaderCircle className="mr-2 size-4 animate-spin" /> : null}保存设置</Button>
                {saved ? <span className="flex items-center gap-1 text-sm text-emerald-600"><Check className="size-4" />已保存</span> : null}
              </div>
            </>
          )}
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </CardContent>
      </Card>
    </section>
  );
}
