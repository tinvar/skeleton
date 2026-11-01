<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Validation\Rules\Max;

/**
 * Which of the account's passkeys to remove, by the id the account page
 * lists it under.
 */
final readonly class RemovePasskeyRequest extends FormRequest
{
    public function __construct(
        #[Max(64)]
        public string $id,
    ) {}
}
