<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/** One choice the new-token form offers: a scope or a lifetime, by its value and what it says. */
final readonly class ApiTokenChoiceData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
