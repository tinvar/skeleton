<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Accounts\Emails;
use Tinvar\Accounts\Passwords;
use Tinvar\Validation\Rules\Email;
use Tinvar\Validation\Rules\Max;

/**
 * The address to move the account to, and its current password.
 */
final readonly class ChangeEmailRequest extends FormRequest
{
    public function __construct(
        #[Email, Max(Emails::MAX_LENGTH)]
        public string $email,
        #[Max(Passwords::MAX_LENGTH)]
        public string $currentPassword,
    ) {}
}
