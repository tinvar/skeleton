<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Accounts\Passwords;
use Tinvar\Validation\Rules\Max;

/**
 * The account's current password, asked before a change to how it signs
 * in.
 */
final readonly class CurrentPasswordRequest extends FormRequest
{
    public function __construct(
        #[Max(Passwords::MAX_LENGTH)]
        public string $currentPassword,
    ) {}
}
