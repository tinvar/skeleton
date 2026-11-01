<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The page a sign-up link lands on: the token to post back, and the
 * address and name it makes an account for -- an empty token and nulls for
 * a link that would not open.
 */
final readonly class CompleteSignUpPageData extends Data
{
    public function __construct(
        public string $token,
        public ?string $email,
        public ?string $name,
        public int $minLength,
    ) {}
}
