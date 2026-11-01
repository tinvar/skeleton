<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The second-factor page: whether a password is waiting on a code, and
 * where to go once it is typed.
 */
final readonly class SecondFactorPageData extends Data
{
    public function __construct(
        public bool $waiting,
        public string $next,
    ) {}
}
