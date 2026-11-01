<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Accounts\Passwords;
use Tinvar\Validation\Rules\Max;
use Tinvar\Validation\Rules\Min;

/**
 * A sign-up link's token and the password chosen for the new account,
 * held to the password policy before the token is so much as looked at.
 */
final readonly class CompleteSignUpRequest extends FormRequest
{
    public function __construct(
        #[Max(2048)]
        public string $token,
        #[Min(Passwords::MIN_LENGTH), Max(Passwords::MAX_LENGTH)]
        public string $password,
    ) {}
}
