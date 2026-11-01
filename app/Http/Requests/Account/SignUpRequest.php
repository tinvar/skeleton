<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Accounts\Emails;
use Tinvar\Validation\Rules\Email;
use Tinvar\Validation\Rules\Max;
use Tinvar\Validation\Rules\Min;

/**
 * An address to make an account at, and the name to greet its owner by.
 * Checked for being an address and nothing else: whether it has an account
 * is never said.
 */
final readonly class SignUpRequest extends FormRequest
{
    public function __construct(
        #[Email, Max(Emails::MAX_LENGTH)]
        public string $email,
        #[Min(1), Max(100)]
        public string $name,
    ) {}
}
