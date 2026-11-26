<?php
declare(strict_types=1);

namespace app\security;

interface PlatformSigner
{
    /** Returns the raw RSA-PSS signature bytes. */
    public function sign(string $keyReference, string $message): string;
}
