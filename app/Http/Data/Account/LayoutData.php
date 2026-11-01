<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * What the shell around every page shows: who is signed in, if anyone.
 */
final readonly class LayoutData extends Data
{
    public function __construct(
        public ?string $userName,
    ) {}
}
