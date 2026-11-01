<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Accounts\Emails;
use Tinvar\Accounts\Passwords;
use Tinvar\Validation\Rules\Email;
use Tinvar\Validation\Rules\Max;

/**
 * What the sign-in form posts. The password is bounded: bcrypt reads only
 * its first 72 bytes, but a megabyte of it is still a megabyte to take in.
 */
final readonly class SignInRequest extends FormRequest
{
    public function __construct(
        #[Email, Max(Emails::MAX_LENGTH)]
        public string $email,
        #[Max(Passwords::MAX_LENGTH)]
        public string $password,
        #[Max(2048)]
        public string $next = '',
    ) {}
}
