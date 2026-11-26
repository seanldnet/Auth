import {
  constants,
  createHash,
  createPrivateKey,
  randomBytes,
  sign as cryptoSign,
  type KeyObject,
} from "node:crypto";

export interface LicenseResult {
  valid: boolean;
  status: string;
  license_no?: string;
  activated_at?: string | null;
  expires_at?: string | null;
  device_label?: string | null;
  machine_change_count?: number;
  idempotent?: boolean;
  message?: string | null;
}

export class LicenseApiError extends Error {
  constructor(public readonly statusCode: number, message: string) {
    super(message);
    this.name = "LicenseApiError";
  }
}

export function buildCanonicalMessage(
  method: string,
  path: string,
  timestamp: string,
  nonce: string,
  body: Uint8Array,
): Buffer {
  if (!path.startsWith("/") || path.includes("?")) {
    throw new TypeError("Signed path must start with / and must not contain a query string");
  }
  const bodyHash = createHash("sha256").update(body).digest("hex");
  return Buffer.from([method.toUpperCase(), path, timestamp, nonce, bodyHash].join("\n"), "utf8");
}

export function signRequest(privateKey: KeyObject, canonicalMessage: Uint8Array): Buffer {
  return cryptoSign("sha256", canonicalMessage, {
    key: privateKey,
    padding: constants.RSA_PKCS1_PSS_PADDING,
    saltLength: 32,
  });
}

export interface LicenseClientOptions {
  baseUrl: string;
  appId: string;
  keyId: string;
  privateKeyPem: string | Buffer;
  fetchImplementation?: typeof fetch;
  timeoutMs?: number;
}

export class LicenseClient {
  private readonly baseUrl: URL;
  private readonly privateKey: KeyObject;
  private readonly fetcher: typeof fetch;
  private readonly timeoutMs: number;

  constructor(private readonly options: LicenseClientOptions) {
    if (!/^app_[a-fA-F0-9]{24}$/.test(options.appId)) throw new TypeError("A valid software App ID is required");
    if (!/^key_[a-f0-9]{16}$/.test(options.keyId)) throw new TypeError("A valid signing Key ID is required");
    this.baseUrl = new URL(options.baseUrl);
    this.privateKey = createPrivateKey(options.privateKeyPem);
    if (this.privateKey.asymmetricKeyType !== "rsa" || (this.privateKey.asymmetricKeyDetails?.modulusLength ?? 0) < 3072) {
      throw new TypeError("RSA-3072 or stronger private key is required");
    }
    this.fetcher = options.fetchImplementation ?? fetch;
    this.timeoutMs = options.timeoutMs ?? 10_000;
  }

  activate(licenseKey: string, deviceFingerprint: string, deviceLabel?: string): Promise<LicenseResult> {
    return this.post("/api/license/activate", {
      app_id: this.options.appId, license_key: licenseKey,
      device_fingerprint: deviceFingerprint, device_label: deviceLabel ?? null,
    });
  }

  verify(licenseKey: string, deviceFingerprint: string): Promise<LicenseResult> {
    return this.post("/api/license/verify", {
      app_id: this.options.appId, license_key: licenseKey, device_fingerprint: deviceFingerprint,
    });
  }

  changeDevice(licenseKey: string, currentFingerprint: string, newFingerprint: string, deviceLabel?: string): Promise<LicenseResult> {
    return this.post("/api/license/change-device", {
      app_id: this.options.appId, license_key: licenseKey,
      current_device_fingerprint: currentFingerprint, new_device_fingerprint: newFingerprint,
      device_label: deviceLabel ?? null,
    });
  }

  unbind(licenseKey: string, deviceFingerprint: string): Promise<LicenseResult> {
    return this.post("/api/license/unbind", {
      app_id: this.options.appId, license_key: licenseKey, device_fingerprint: deviceFingerprint,
    });
  }

  private async post(path: string, payload: Record<string, unknown>): Promise<LicenseResult> {
    const body = Buffer.from(JSON.stringify(payload), "utf8");
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = randomBytes(32).toString("hex");
    const canonical = buildCanonicalMessage("POST", path, timestamp, nonce, body);
    const signature = signRequest(this.privateKey, canonical).toString("base64");
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);
    try {
      const response = await this.fetcher(new URL(path, this.baseUrl), {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-App-Id": this.options.appId,
          "X-Key-Id": this.options.keyId,
          "X-Timestamp": timestamp,
          "X-Nonce": nonce,
          "X-Signature": signature,
        },
        body,
        signal: controller.signal,
      });
      const envelope = await response.json() as {
        success?: boolean; data?: LicenseResult; message?: string;
      };
      if (!response.ok || !envelope.success || !envelope.data) {
        throw new LicenseApiError(response.status, envelope.message ?? "License API request failed");
      }
      return envelope.data;
    } finally {
      clearTimeout(timer);
    }
  }
}
