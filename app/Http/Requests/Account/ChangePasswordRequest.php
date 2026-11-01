<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Accounts\Passwords;
use Tinvar\Validation\Rules\Max;
use Tinvar\Validation\Rules\Min;

/**
 * The current password, and the new one.
 */
final readonly class ChangePasswordRequest extends FormRequest
{
    public function __construct(
        #[Max(Passwords::MAX_LENGTH)]
        public string $currentPassword,
        #[Min(Passwords::MIN_LENGTH), Max(Passwords::MAX_LENGTH)]
        public string $password,
    ) {}
}
