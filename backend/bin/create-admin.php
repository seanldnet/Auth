#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use think\App;
use think\facade\Db;

$app = new App();
$app->initialize();

$username = trim((string) getenv('AUTH_BOOTSTRAP_ADMIN_USERNAME'));
$password = (string) getenv('AUTH_BOOTSTRAP_ADMIN_PASSWORD');

if ($username === '' || $password === '' || strlen($password) < 16) {
    fwrite(STDERR, "Set AUTH_BOOTSTRAP_ADMIN_USERNAME and AUTH_BOOTSTRAP_ADMIN_PASSWORD (16+ characters) in the process environment.\n");
    exit(1);
}

if (Db::name('users')->count() > 0) {
    fwrite(STDERR, "Bootstrap refused: auth_users is not empty. Use the existing administrator account.\n");
    exit(1);
}

$algorithm = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
$passwordHash = password_hash($password, $algorithm);
if (!is_string($passwordHash)) {
    fwrite(STDERR, "Unable to hash the bootstrap password.\n");
    exit(1);
}

Db::name('users')->insert([
    'username' => $username,
    'password_hash' => $passwordHash,
    'role' => 'ADMIN',
    'status' => 'ACTIVE',
    'must_change_password' => 1,
    'password_updated_at' => gmdate('Y-m-d H:i:s'),
]);

fwrite(STDOUT, "Bootstrap administrator created. Sign in and change the password before configuring the platform.\n");
