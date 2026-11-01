<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The sign-up page: whether a link was just asked for, and the shortest
 * password the next step will take.
 */
final readonly class SignUpPageData extends Data
{
    public function __construct(
        public bool $sent,
        public int $minLength,
    ) {}
}
