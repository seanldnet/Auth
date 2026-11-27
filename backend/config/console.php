<?php
return [
    'commands' => [
        'auth:notify-expiring-licenses' => app\command\NotifyExpiringLicenses::class,
        'auth:cleanup-api-key-nonces' => app\command\CleanupApiKeyNonces::class,
    ],
];
