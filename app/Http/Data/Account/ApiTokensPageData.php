<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The API tokens page: the account's tokens, newest first, and what a new
 * one may be issued with -- the scopes the app's API declares and the
 * lifetimes it offers.
 */
final readonly class ApiTokensPageData extends Data
{
    /**
     * @param list<ApiTokenData> $tokens
     * @param list<ApiTokenChoiceData> $scopes
     * @param list<ApiTokenChoiceData> $lifetimes
     */
    public function __construct(
        public array $tokens,
        public array $scopes,
        public array $lifetimes,
        public int $maxTokens,
    ) {}
}
