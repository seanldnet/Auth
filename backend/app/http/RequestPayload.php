<?php
declare(strict_types=1);

namespace app\http;

use JsonException;
use think\Request;

final class RequestPayload
{
    /** @return array<string, mixed> */
    public static function all(Request $request): array
    {
        $payload = array_merge($request->get(), $request->post());
        $contentType = strtolower((string) $request->header('content-type', ''));

        if (str_contains($contentType, 'application/json')) {
            try {
                $json = json_decode((string) $request->getInput(), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($json)) {
                    $payload = array_merge($payload, $json);
                }
            } catch (JsonException) {
                return [];
            }
        }

        return $payload;
    }
}
