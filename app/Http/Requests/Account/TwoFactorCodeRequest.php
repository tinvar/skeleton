<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Validation\Rules\Max;

/**
 * A code from the authenticator app being set up.
 */
final readonly class TwoFactorCodeRequest extends FormRequest
{
    public function __construct(
        #[Max(32)]
        public string $code,
    ) {}
}
