<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * Two-factor setup half-way: the secret to type into an authenticator app,
 * and the otpauth:// URI a QR code would carry.
 */
final readonly class TwoFactorSetupData extends Data
{
    public function __construct(
        public string $secret,
        public string $uri,
    ) {}
}
