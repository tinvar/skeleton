<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Validation\Rules\Max;

/**
 * A passkey's signed answer to the challenge that confirms a session, as
 * PublicKeyCredential.toJSON() writes it.
 */
final readonly class PasskeyConfirmRequest extends FormRequest
{
    public function __construct(
        #[Max(65536)]
        public string $credential,
    ) {}
}
