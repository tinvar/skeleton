<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Validation\Rules\Max;

/**
 * A code from an authenticator app, or a recovery code.
 */
final readonly class SecondFactorRequest extends FormRequest
{
    public function __construct(
        #[Max(32)]
        public string $code,
        #[Max(2048)]
        public string $next = '',
    ) {}
}
