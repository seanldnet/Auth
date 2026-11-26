<?php
declare(strict_types=1);

namespace app\controller;

use app\http\RequestPayload;
use app\license\LicenseRuntimeService;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\response\Json;

final class LicenseRuntime
{
    public function activate(Request $request): Json
    {
        return $this->respond($request, fn(array $p) => (new LicenseRuntimeService())->activate(
            (string)($p['app_id'] ?? ''), (string)($p['license_key'] ?? ''),
            (string)($p['device_fingerprint'] ?? ''), (string)($p['device_label'] ?? ''),
            (string)$request->ip(), (string)$request->header('user-agent', ''),
        ), 200);
    }

    public function verify(Request $request): Json
    {
        return $this->respond($request, fn(array $p) => (new LicenseRuntimeService())->verify(
            (string)($p['app_id'] ?? ''), (string)($p['license_key'] ?? ''),
            (string)($p['device_fingerprint'] ?? ''), (string)$request->ip(),
            (string)$request->header('user-agent', ''),
        ));
    }

    public function changeDevice(Request $request): Json
    {
        return $this->respond($request, fn(array $p) => (new LicenseRuntimeService())->changeDevice(
            (string)($p['app_id'] ?? ''), (string)($p['license_key'] ?? ''),
            (string)($p['current_device_fingerprint'] ?? ''), (string)($p['new_device_fingerprint'] ?? ''),
            (string)($p['device_label'] ?? ''), (string)$request->ip(), (string)$request->header('user-agent', ''),
        ));
    }

    public function unbind(Request $request): Json
    {
        return $this->respond($request, fn(array $p) => (new LicenseRuntimeService())->unbind(
            (string)($p['app_id'] ?? ''), (string)($p['license_key'] ?? ''),
            (string)($p['device_fingerprint'] ?? ''), (string)$request->ip(),
            (string)$request->header('user-agent', ''),
        ));
    }

    private function respond(Request $request, callable $operation, int $status = 200): Json
    {
        $payload = RequestPayload::all($request);
        try {
            return json(['success' => true, 'data' => $operation($payload)], $status);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 403);
        }
    }
}
