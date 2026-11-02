<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Carbon\CarbonImmutable;
use Tinvar\Api\ApiTokenStatus;
use Tinvar\Data\Data;

/**
 * One of the account's API tokens, as the API tokens page lists it: never
 * its secret, which nothing kept can show.
 */
final readonly class ApiTokenData extends Data
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $id,
        public string $name,
        public array $scopes,
        public ApiTokenStatus $status,
        public string $statusLabel,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $expiresAt,
        public ?CarbonImmutable $lastUsedAt,
    ) {}
}
