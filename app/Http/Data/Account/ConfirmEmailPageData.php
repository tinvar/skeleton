<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The page a change-of-address link lands on: the token to post back, the
 * address it moves the account to, and from which.
 */
final readonly class ConfirmEmailPageData extends Data
{
    public function __construct(
        public string $token,
        public ?string $email,
        public ?string $newEmail,
    ) {}
}
