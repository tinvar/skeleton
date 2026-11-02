<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * The API tokens page: the account's tokens, newest first, and what a new
 * one may be issued with -- the scopes the app's API declares and the
 * lifetimes it offers -- with what the "Confirm it's you" dialog needs:
 * until when the session's last confirmation counts, and the ways the
 * account can confirm.
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
        public ?int $confirmedUntil = null,
        public bool $hasPasskeys = false,
        public bool $twoFactorEnabled = false,
    ) {}
}
