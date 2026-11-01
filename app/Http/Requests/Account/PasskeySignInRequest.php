<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Validation\Rules\Max;

/**
 * A passkey's signed answer to the sign-in challenge, as
 * PublicKeyCredential.toJSON() writes it.
 */
final readonly class PasskeySignInRequest extends FormRequest
{
    public function __construct(
        #[Max(65536)]
        public string $credential,
        #[Max(2048)]
        public string $next = '',
    ) {}
}
