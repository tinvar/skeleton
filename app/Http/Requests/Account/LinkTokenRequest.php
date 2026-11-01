<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Tinvar\Validation\FormRequest;
use Tinvar\Validation\Rules\Max;

/**
 * An emailed link's token, POSTed back from the page it landed on: the GET
 * only shows the page, so a mail scanner that opens the link uses nothing.
 */
final readonly class LinkTokenRequest extends FormRequest
{
    public function __construct(
        #[Max(2048)]
        public string $token,
    ) {}
}
