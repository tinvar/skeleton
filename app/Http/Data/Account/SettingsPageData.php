<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The account page: who is signed in, their passkeys, whether two-factor
 * sign-in is on -- and, half-way through turning it on, the secret -- and
 * what an action just did, said back.
 */
final readonly class SettingsPageData extends Data
{
    /** @param list<PasskeyData> $passkeys */
    public function __construct(
        public string $name,
        public string $email,
        public array $passkeys,
        public bool $twoFactorEnabled,
        public int $recoveryCodesLeft,
        public ?TwoFactorSetupData $twoFactorSetup,
        public ?string $notice,
        public int $minLength,
    ) {}
}
