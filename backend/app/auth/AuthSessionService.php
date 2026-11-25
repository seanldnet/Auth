<?php
declare(strict_types=1);

namespace app\auth;

use RuntimeException;
use think\facade\Db;

final class AuthSessionService
{
    public const SESSION_TTL_SECONDS = 43200;
    private const LOGIN_WINDOW_SECONDS = 900;
    private const MAX_FAILED_ATTEMPTS = 5;

    /**
     * @return array{token:string,expires_at:string,user:array<string,mixed>}
     */
    public function login(string $username, string $password, string $ipAddress, string $userAgent): array
    {
        $username = trim($username);
        if ($username === '' || strlen($username) > 64 || $password === '' || strlen($password) > 1024) {
            throw new RuntimeException('用户名或密码错误。');
        }

        $identityHash = hash('sha256', mb_strtolower($username, 'UTF-8'));
        $ipHash = hash('sha256', $ipAddress !== '' ? $ipAddress : 'unknown');
        $attemptRows = Db::query(
            'SELECT failed_count, locked_until FROM auth_login_attempts WHERE identity_hash = ? AND ip_hash = ? LIMIT 1',
            [$identityHash, $ipHash],
        );
        $attempt = $attemptRows[0] ?? null;
        if (is_array($attempt) && !empty($attempt['locked_until'])
            && strtotime((string) $attempt['locked_until'] . ' UTC') > time()
        ) {
            throw new RuntimeException('登录尝试次数过多，请稍后再试。');
        }

        $userRows = Db::query(
            'SELECT id, username, password_hash, role, status, must_change_password
             FROM auth_users WHERE username = ? LIMIT 1',
            [$username],
        );
        $user = $userRows[0] ?? null;

        if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
            $this->recordFailedAttempt($identityHash, $ipHash);
            $this->audit(null, 'LOGIN_FAILED', $ipAddress, $userAgent, [
                'identity_hash' => $identityHash,
            ]);
            throw new RuntimeException('用户名或密码错误。');
        }

        if ((string) $user['status'] !== 'ACTIVE') {
            $this->audit((int) $user['id'], 'LOGIN_BLOCKED', $ipAddress, $userAgent, [
                'account_status' => (string) $user['status'],
            ]);
            throw new RuntimeException('账号当前不可登录，请联系管理员。');
        }

        $algorithm = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        if (password_needs_rehash((string) $user['password_hash'], $algorithm)) {
            $newHash = password_hash($password, $algorithm);
            if (!is_string($newHash)) {
                throw new RuntimeException('Unable to upgrade the stored password hash.');
            }
            Db::execute(
                'UPDATE auth_users SET password_hash = ?, password_updated_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$newHash, (int) $user['id']],
            );
        }

        Db::execute(
            'DELETE FROM auth_login_attempts WHERE identity_hash = ? AND ip_hash = ?',
            [$identityHash, $ipHash],
        );

        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::SESSION_TTL_SECONDS);
        Db::execute(
            'INSERT INTO auth_user_sessions
                (user_id, token_hash, ip_address, user_agent, expires_at, last_seen_at, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [
                (int) $user['id'],
                hash('sha256', $token),
                $ipAddress !== '' ? $ipAddress : null,
                mb_substr($userAgent, 0, 255, 'UTF-8'),
                $expiresAt,
            ],
        );
        Db::execute(
            'UPDATE auth_users SET last_login_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [(int) $user['id']],
        );
        $this->audit((int) $user['id'], 'LOGIN_SUCCEEDED', $ipAddress, $userAgent);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => self::safeUser($user),
        ];
    }

    /** @return array<string, mixed>|null */
    public function authenticate(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $rows = Db::query(
            'SELECT s.id AS session_id, s.user_id, s.expires_at,
                    u.username, u.role, u.status, u.must_change_password
             FROM auth_user_sessions s
             INNER JOIN auth_users u ON u.id = s.user_id
             WHERE s.token_hash = ? AND s.revoked_at IS NULL
               AND s.expires_at > UTC_TIMESTAMP()
             LIMIT 1',
            [hash('sha256', $token)],
        );
        $session = $rows[0] ?? null;
        if (!is_array($session) || (string) $session['status'] !== 'ACTIVE') {
            return null;
        }

        Db::execute(
            'UPDATE auth_user_sessions
             SET last_seen_at = UTC_TIMESTAMP()
             WHERE id = ? AND (last_seen_at IS NULL OR last_seen_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))',
            [(int) $session['session_id']],
        );

        return [
            'id' => (int) $session['user_id'],
            'username' => (string) $session['username'],
            'role' => (string) $session['role'],
            'must_change_password' => (bool) $session['must_change_password'],
            'session_id' => (int) $session['session_id'],
            'expires_at' => (string) $session['expires_at'],
        ];
    }

    public function logout(string $token, string $ipAddress = '', string $userAgent = ''): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return;
        }

        $rows = Db::query(
            'SELECT id, user_id FROM auth_user_sessions WHERE token_hash = ? AND revoked_at IS NULL LIMIT 1',
            [hash('sha256', $token)],
        );
        $session = $rows[0] ?? null;
        if (!is_array($session)) {
            return;
        }

        Db::execute(
            'UPDATE auth_user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND revoked_at IS NULL',
            [(int) $session['id']],
        );
        $this->audit((int) $session['user_id'], 'LOGOUT', $ipAddress, $userAgent);
    }

    /**
     * @param array<string, mixed>|null $user
     * @return array<string, mixed>
     */
    private static function safeUser(?array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'role' => (string) $user['role'],
            'must_change_password' => (bool) $user['must_change_password'],
        ];
    }

    private function recordFailedAttempt(string $identityHash, string $ipHash): void
    {
        Db::execute(
            'INSERT INTO auth_login_attempts
                (identity_hash, ip_hash, failed_count, window_started_at, locked_until, updated_at)
             VALUES (?, ?, 1, UTC_TIMESTAMP(), NULL, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                failed_count = IF(window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), 1, failed_count + 1),
                window_started_at = IF(window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), UTC_TIMESTAMP(), window_started_at),
                locked_until = IF(failed_count >= 5, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), locked_until),
                updated_at = UTC_TIMESTAMP()',
            [$identityHash, $ipHash],
        );
    }

    /** @param array<string, scalar|null> $metadata */
    private function audit(?int $userId, string $event, string $ipAddress, string $userAgent, array $metadata = []): void
    {
        Db::execute(
            'INSERT INTO auth_audit_logs
                (actor_user_id, event_type, ip_address, user_agent, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                $userId,
                $event,
                $ipAddress !== '' ? $ipAddress : null,
                mb_substr($userAgent, 0, 255, 'UTF-8'),
                $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ],
        );
    }
}
