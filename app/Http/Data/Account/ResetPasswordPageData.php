<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The page a reset link lands on: the token to post back and the account
 * it is for -- an empty token and null for a link that would not open.
 */
final readonly class ResetPasswordPageData extends Data
{
    public function __construct(
        public string $token,
        public ?string $email,
        public int $minLength,
    ) {}
}
