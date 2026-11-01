<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The sign-in page: where to go once signed in.
 */
final readonly class SignInPageData extends Data
{
    public function __construct(
        public string $next,
    ) {}
}
