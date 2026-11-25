import { useEffect, useState } from "react";
import { AlertCircle, CheckCircle2, CreditCard, LoaderCircle, Wallet } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

type Channel = {
  channel_code: string;
  display_name: string;
  adapter_available: boolean;
  enabled: boolean;
  allowed_currencies: string[];
  merchant_pid: string;
  has_credentials: boolean;
  configuration: Record<string, unknown>;
};

type FormState = {
  displayName: string;
  merchantPid: string;
  enabled: boolean;
  allowedCurrencies: string[];
  secretKey: string;
  baseUrl: string;
  notifyUrl: string;
  returnUrl: string;
  checkoutHosts: string;
  providerCny: string;
  providerUsdt: string;
  sendCurrencyOverride: boolean;
  epayDefaultCurrency: string;
  orderTtlSeconds: string;
};

function formFrom(channel: Channel): FormState {
  const config = channel.configuration || {};
  const currencyMap = (config.provider_currency_by_platform_currency || {}) as Record<string, string>;
  return {
    displayName: channel.display_name,
    merchantPid: channel.merchant_pid || "",
    enabled: channel.enabled,
    allowedCurrencies: channel.allowed_currencies || [],
    secretKey: "",
    baseUrl: String(config.base_url || ""),
    notifyUrl: String(config.notify_url || ""),
    returnUrl: String(config.return_url || ""),
    checkoutHosts: Array.isArray(config.checkout_hosts) ? config.checkout_hosts.join(", ") : "",
    providerCny: String(currencyMap.CNY || ""),
    providerUsdt: String(currencyMap.USDT || ""),
    sendCurrencyOverride: Boolean(config.send_currency_override),
    epayDefaultCurrency: String(config.epay_default_currency || ""),
    orderTtlSeconds: String(config.order_ttl_seconds || 900),
  };
}

function PaymentChannelCard({ channel, onSaved }: { channel: Channel; onSaved: (channel: Channel) => void }) {
  const [form, setForm] = useState<FormState>(() => formFrom(channel));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState(false);

  useEffect(() => setForm(formFrom(channel)), [channel]);

  function update<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
    setSaved(false);
  }

  function toggleCurrency(currency: string) {
    const next = form.allowedCurrencies.includes(currency)
      ? form.allowedCurrencies.filter((item) => item !== currency)
      : [...form.allowedCurrencies, currency];
    update("allowedCurrencies", next);
  }

  async function save() {
    setSaving(true);
    setError("");
    setSaved(false);
    try {
      const response = await fetch("/api/admin/payment-channels/" + encodeURIComponent(channel.channel_code), {
        method: "PUT",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          display_name: form.displayName,
          merchant_pid: form.merchantPid,
          enabled: form.enabled,
          allowed_currencies: form.allowedCurrencies,
          credentials: { secret_key: form.secretKey },
          configuration: {
            base_url: form.baseUrl,
            notify_url: form.notifyUrl,
            return_url: form.returnUrl,
            checkout_hosts: form.checkoutHosts.split(",").map((host) => host.trim()).filter(Boolean),
            provider_currency_by_platform_currency: {
              CNY: form.providerCny,
              USDT: form.providerUsdt,
            },
            send_currency_override: form.sendCurrencyOverride,
            epay_default_currency: form.epayDefaultCurrency,
            order_ttl_seconds: Number(form.orderTtlSeconds),
          },
        }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || "保存支付渠道配置失败");
      }
      onSaved(result.data as Channel);
      setForm((current) => ({ ...current, secretKey: "" }));
      setSaved(true);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "保存失败");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <CardHeader className="gap-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-col gap-1">
            <CardTitle className="text-base">{channel.display_name}</CardTitle>
            <CardDescription>{channel.channel_code} · {channel.adapter_available ? "适配器已实现" : "官方适配器开发中"}</CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Badge variant={channel.enabled ? "success" : "outline"}>{channel.enabled ? "已启用" : "未启用"}</Badge>
            {channel.has_credentials ? <Badge variant="secondary">凭据已保存</Badge> : null}
          </div>
        </div>
      </CardHeader>

      <CardContent className="flex flex-col gap-5">
        {!channel.adapter_available ? (
          <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-muted-foreground">
            该官方支付适配器尚未完成，当前只能查看规划状态，不能启用。
          </div>
        ) : null}

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium">渠道显示名称</label>
            <Input value={form.displayName} onChange={(event) => update("displayName", event.target.value)} />
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium">商户 PID / 商户号</label>
            <Input value={form.merchantPid} onChange={(event) => update("merchantPid", event.target.value)} placeholder="填写商户 PID" autoComplete="off" />
          </div>
          <div className="flex flex-col gap-2 md:col-span-2">
            <label className="text-sm font-medium">商户 Secret</label>
            <Input type="password" value={form.secretKey} onChange={(event) => update("secretKey", event.target.value)} placeholder={channel.has_credentials ? "留空表示保持当前密钥不变" : "首次配置必须填写"} autoComplete="new-password" />
            <p className="text-xs text-muted-foreground">密钥只写入加密存储，不会回显；更换密钥时填写新值。</p>
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium">支付网关 API 根地址</label>
            <Input value={form.baseUrl} onChange={(event) => update("baseUrl", event.target.value)} placeholder="https://gateway.example.com" />
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium">异步回调地址（HTTPS）</label>
            <Input value={form.notifyUrl} onChange={(event) => update("notifyUrl", event.target.value)} placeholder={"https://your-domain/api/payments/callback/" + channel.channel_code} />
          </div>
          <div className="flex flex-col gap-2 md:col-span-2">
            <label className="text-sm font-medium">同步返回地址（可选）</label>
            <Input value={form.returnUrl} onChange={(event) => update("returnUrl", event.target.value)} placeholder="https://your-domain/payment/return" />
          </div>
          <div className="flex flex-col gap-2 md:col-span-2">
            <label className="text-sm font-medium">收银台域名白名单</label>
            <Input value={form.checkoutHosts} onChange={(event) => update("checkoutHosts", event.target.value)} placeholder="checkout.example.com, pay.example.com" />
            <p className="text-xs text-muted-foreground">多个域名用英文逗号分隔。渠道返回的收银台 URL 不在白名单时会被拒绝。</p>
          </div>
        </div>

        <div className="flex flex-col gap-3 rounded-lg border p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-col gap-1">
              <p className="text-sm font-medium">平台可用币种</p>
              <p className="text-xs text-muted-foreground">CNY 与 USDT 独立记账，不进行汇率换算。</p>
            </div>
            <div className="flex gap-2">
              {["CNY", "USDT"].filter((currency) => channel.channel_code !== "ALIPAY" && channel.channel_code !== "WECHAT_PAY" || currency === "CNY").map((currency) => (
                <Button key={currency} type="button" size="sm" variant={form.allowedCurrencies.includes(currency) ? "default" : "outline"} onClick={() => toggleCurrency(currency)}>
                  {currency === "USDT" ? "U · USDT" : "¥ · CNY"}
                </Button>
              ))}
            </div>
          </div>

          {channel.adapter_available ? (
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div className="flex flex-col gap-2">
                <label className="text-sm font-medium">供应商 CNY 计价币种</label>
                <Input value={form.providerCny} onChange={(event) => update("providerCny", event.target.value.toLowerCase())} placeholder="cny" />
              </div>
              <div className="flex flex-col gap-2">
                <label className="text-sm font-medium">供应商 USDT 计价币种</label>
                <Input value={form.providerUsdt} onChange={(event) => update("providerUsdt", event.target.value.toLowerCase())} placeholder="usd" />
              </div>
            </div>
          ) : null}
        </div>

        {channel.channel_code === "EPAY" ? (
          <div className="flex flex-col gap-3 rounded-lg border p-4">
            <p className="text-sm font-medium">易支付协议选项</p>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex flex-col gap-1">
                <p className="text-sm">向网关显式发送 currency 参数</p>
                <p className="text-xs text-muted-foreground">仅在商户接口版本支持时开启；否则必须与网关默认币种一致。</p>
              </div>
              <Button type="button" size="sm" variant={form.sendCurrencyOverride ? "default" : "outline"} onClick={() => update("sendCurrencyOverride", !form.sendCurrencyOverride)}>
                {form.sendCurrencyOverride ? "已开启" : "未开启"}
              </Button>
            </div>
            <div className="flex flex-col gap-2">
              <label className="text-sm font-medium">易支付网关默认币种</label>
              <Input value={form.epayDefaultCurrency} onChange={(event) => update("epayDefaultCurrency", event.target.value.toLowerCase())} placeholder="cny 或 usd" />
            </div>
          </div>
        ) : null}

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium">订单有效期（秒）</label>
            <Input type="number" min={60} max={3600} value={form.orderTtlSeconds} onChange={(event) => update("orderTtlSeconds", event.target.value)} />
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium">渠道启用状态</label>
            <Button type="button" variant={form.enabled ? "default" : "outline"} disabled={!channel.adapter_available} onClick={() => update("enabled", !form.enabled)}>
              {form.enabled ? "当前启用 · 点击禁用" : "当前禁用 · 点击启用"}
            </Button>
          </div>
        </div>

        {error ? (
          <div role="alert" className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
            <AlertCircle className="mt-0.5 size-4 shrink-0" />
            <p>{error}</p>
          </div>
        ) : null}
        {saved ? (
          <div className="flex items-center gap-2 text-sm text-emerald-700">
            <CheckCircle2 className="size-4" />
            渠道配置已保存
          </div>
        ) : null}

        <div className="flex justify-end border-t pt-4">
          <Button type="button" onClick={save} disabled={saving || !channel.adapter_available}>
            {saving ? <LoaderCircle className="size-4 animate-spin" /> : null}
            {saving ? "正在保存…" : "保存渠道配置"}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

export function PaymentsPage() {
  const [channels, setChannels] = useState<Channel[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    fetch("/api/admin/payment-channels", {
      credentials: "include",
      headers: { Accept: "application/json" },
    })
      .then(async (response) => {
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || "读取支付渠道失败");
        setChannels(result.data as Channel[]);
      })
      .catch((cause) => setError(cause instanceof Error ? cause.message : "读取支付渠道失败"))
      .finally(() => setLoading(false));
  }, []);

  function updateChannel(updated: Channel) {
    setChannels((current) => current.map((channel) => channel.channel_code === updated.channel_code ? updated : channel));
  }

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium text-primary">财务中心 / 支付配置</p>
        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">支付渠道与收银台</h1>
        <p className="max-w-3xl text-sm leading-6 text-muted-foreground">
          独立配置 EPUSDT、GMPay 与易支付商户接口。收银台地址只接受渠道响应中的 HTTPS URL，并执行域名白名单校验。
          CNY 与 USDT 分别记账，供应商金额与平台金额保持相同，不做汇率换算。
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardHeader className="flex-row items-center justify-between gap-4">
            <div className="flex flex-col gap-1.5">
              <CardTitle className="text-base">人民币账户</CardTitle>
              <CardDescription>独立 CNY 余额与订单</CardDescription>
            </div>
            <Wallet className="size-5 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">¥ CNY</p>
            <p className="mt-2 text-xs text-muted-foreground">人民币订单独立结算</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex-row items-center justify-between gap-4">
            <div className="flex flex-col gap-1.5">
              <CardTitle className="text-base">USDT 账户</CardTitle>
              <CardDescription>独立 USDT 余额与订单</CardDescription>
            </div>
            <CreditCard className="size-5 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">U · USDT</p>
            <p className="mt-2 text-xs text-muted-foreground">USDT 订单独立结算</p>
          </CardContent>
        </Card>
      </div>

      {error ? (
        <div role="alert" className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
          <AlertCircle className="mt-0.5 size-4 shrink-0" />
          <p>{error}</p>
        </div>
      ) : null}

      {loading ? (
        <Card>
          <CardContent className="flex items-center justify-center gap-3 p-8 text-sm text-muted-foreground">
            <LoaderCircle className="size-5 animate-spin" />
            正在读取支付渠道配置…
          </CardContent>
        </Card>
      ) : (
        <div className="flex flex-col gap-5">
          {channels.map((channel) => (
            <PaymentChannelCard key={channel.channel_code} channel={channel} onSaved={updateChannel} />
          ))}
        </div>
      )}
    </section>
  );
}
