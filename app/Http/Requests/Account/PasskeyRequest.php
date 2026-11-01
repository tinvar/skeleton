<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Validation\Rules\Max;

/**
 * A passkey the browser made, as PublicKeyCredential.toJSON() writes it,
 * and what to call it.
 */
final readonly class PasskeyRequest extends FormRequest
{
    public function __construct(
        #[Max(65536)]
        public string $credential,
        #[Max(100)]
        public string $name = '',
    ) {}
}
