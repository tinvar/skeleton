<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The page that asks for a reset link: whether one was just asked for.
 */
final readonly class ForgotPasswordPageData extends Data
{
    public function __construct(
        public bool $sent,
    ) {}
}
