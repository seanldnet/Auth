#!/usr/bin/env php
<?php
declare(strict_types=1);

use think\App;
use think\facade\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new App();
$app->initialize();

$username = trim((string)(getenv('AUTH_BOOTSTRAP_ADMIN_USERNAME') ?: 'admin'));
$password = (string)(getenv('AUTH_BOOTSTRAP_ADMIN_PASSWORD') ?: '123456');

if ($username === '' || $password === '') {
    fwrite(STDERR, "Bootstrap administrator credentials must not be empty.\n");
    exit(1);
}

if (Db::name('users')->count() > 0) {
    fwrite(STDOUT, "Administrator bootstrap skipped: users already exist.\n");
    exit(0);
}

$algorithm = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
$passwordHash = password_hash($password, $algorithm);
if (!is_string($passwordHash)) {
    fwrite(STDERR, "Unable to hash the bootstrap password.\n");
    exit(1);
}

Db::transaction(static function () use ($username, $passwordHash): void {
    $userId = Db::name('users')->insertGetId([
        'username' => $username,
        'password_hash' => $passwordHash,
        'role' => 'ADMIN',
        'status' => 'ACTIVE',
        'must_change_password' => 1,
        'password_updated_at' => gmdate('Y-m-d H:i:s'),
    ]);

    // The platform has one wallet account and one numeric balance per user.
    Db::name('wallet_accounts')->insert([
        'user_id' => $userId,
        'balance' => '0',
    ]);
});

fwrite(STDOUT, "Bootstrap administrator created. Username: {$username}. Change the default password after first login.\n");
