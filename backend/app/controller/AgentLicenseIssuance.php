<?php
declare(strict_types=1);

namespace app\\controller;

use app\\auth\\AuthContext;
use app\\http\\RequestPayload;
use app\\license\\AgentLicenseIssuanceService;
use InvalidArgumentException;
use RuntimeException;
use think\\Request;
use think\\response\\Json;

final class AgentLicenseIssuance
{
    public function create(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null || !in_array(($actor['role'] ?? ''), ['AGENT', 'SUB_AGENT'], true)) {
            return json(['success' => false, 'message' => '只有代理或下级代理可以通过此接口使用授权额度。'], 403);
        }
        $payload = RequestPayload::all($request);
        try {
            $result = (new AgentLicenseIssuanceService())->issue(
                (int)$actor['id'],
                (int)($payload['software_id'] ?? 0),
                (int)($payload['plan_id'] ?? 0),
                (int)($payload['quantity'] ?? 0),
                (string)$request->header('Idempotency-Key', ''),
                (string)$request->ip(),
                (string)$request->header('user-agent', ''),
            );
            return json(['success' => true, 'data' => $result], 201);
        } catch (InvalidArgumentException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
