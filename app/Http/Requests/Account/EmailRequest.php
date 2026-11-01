<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Accounts\Emails;
use Tinvar\Validation\Rules\Email;
use Tinvar\Validation\Rules\Max;

/**
 * An address to send a reset link to: whether it has an account is never
 * said.
 */
final readonly class EmailRequest extends FormRequest
{
    public function __construct(
        #[Email, Max(Emails::MAX_LENGTH)]
        public string $email,
    ) {}
}
