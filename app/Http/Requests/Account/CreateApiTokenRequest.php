<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Http\Data\Account\TokenLifetime;
use Tinvar\Api\ApiToken;
use Tinvar\Validation\FormRequest;
use Tinvar\Validation\Rules\Max;
use Tinvar\Validation\Rules\Min;

/**
 * A new API token, as the API tokens page asks for one: what it is for,
 * the scopes it may be used for, and how long it lasts. Which scopes there
 * are is the API's to say (ApiTokens::grantable()), so ApiTokens holds the
 * scopes to it, and says which will do.
 */
final readonly class CreateApiTokenRequest extends FormRequest
{
    /** @param list<string> $scopes */
    public function __construct(
        #[Min(1), Max(ApiToken::MAX_NAME_LENGTH)]
        public string $name,
        #[Min(1), Max(16)]
        public array $scopes,
        public TokenLifetime $lifetime = TokenLifetime::ThirtyDays,
    ) {}
}
