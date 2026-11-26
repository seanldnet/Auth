import test from "node:test";
import assert from "node:assert/strict";
import {
  constants, generateKeyPairSync, verify, createHash,
} from "node:crypto";
import { buildCanonicalMessage, signRequest } from "../dist/index.js";

test("canonical request hashes the exact body and uppercases the method", () => {
  const body = Buffer.from('{"app_id":"app_123"}');
  const canonical = buildCanonicalMessage("post", "/api/license/verify", "1795700000", "0123456789abcdef0123456789abcdef", body);
  assert.ok(canonical.toString().startsWith("POST\n/api/license/verify\n1795700000\n0123456789abcdef0123456789abcdef\n"));
  assert.ok(canonical.toString().endsWith(requireHash(body)));
});

test("RSA-PSS signature verifies and rejects tampering", () => {
  const { privateKey, publicKey } = generateKeyPairSync("rsa", { modulusLength: 3072 });
  const message = Buffer.from("signed request");
  const signature = signRequest(privateKey, message);
  assert.equal(verify("sha256", message, { key: publicKey, padding: constants.RSA_PKCS1_PSS_PADDING, saltLength: 32 }, signature), true);
  assert.equal(verify("sha256", Buffer.concat([message, Buffer.from("x")]), { key: publicKey, padding: constants.RSA_PKCS1_PSS_PADDING, saltLength: 32 }, signature), false);
});

test("canonical path rejects query strings", () => {
  assert.throws(() => buildCanonicalMessage("POST", "/api/license/verify?x=1", "1", "0123456789abcdef", Buffer.alloc(0)));
});

function requireHash(body) {
  return createHashHex(body);
}
function createHashHex(body) {
  return createHash("sha256").update(body).digest("hex");
}
