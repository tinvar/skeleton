<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;
use Carbon\CarbonImmutable;

/**
 * One of the account's passkeys, as the settings page lists it.
 */
final readonly class PasskeyData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $backedUp,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $lastUsedAt,
    ) {}
}
