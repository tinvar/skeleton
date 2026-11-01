<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Accounts\Passwords;
use Tinvar\Validation\Rules\Max;
use Tinvar\Validation\Rules\Min;

/**
 * A reset link's token and the new password, held to the password policy
 * before the token is looked at, so a refused password leaves the link
 * unused.
 */
final readonly class ResetPasswordRequest extends FormRequest
{
    public function __construct(
        #[Max(2048)]
        public string $token,
        #[Min(Passwords::MIN_LENGTH), Max(Passwords::MAX_LENGTH)]
        public string $password,
    ) {}
}
