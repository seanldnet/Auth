<?php
declare(strict_types=1);

namespace app\auth;

use think\Request;
use WeakMap;

final class AuthContext
{
    /** @var WeakMap<Request, array<string, mixed>>|null */
    private static ?WeakMap $users = null;

    /** @param array<string, mixed> $user */
    public static function set(Request $request, array $user): void
    {
        self::$users ??= new WeakMap();
        self::$users[$request] = $user;
    }

    /** @return array<string, mixed>|null */
    public static function get(Request $request): ?array
    {
        return self::$users[$request] ?? null;
    }
}
