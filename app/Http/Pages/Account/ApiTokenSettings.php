<?php

declare(strict_types=1);

namespace App\Http\Pages\Account;

use App\Http\Data\Account\ApiTokenChoiceData;
use App\Http\Data\Account\ApiTokenData;
use App\Http\Data\Account\ApiTokensPageData;
use App\Http\Data\Account\TokenLifetime;
use Tinvar\Accounts\RequireSignIn;
use Tinvar\Api\ApiTokens;
use Tinvar\Attributes\Middleware;
use Tinvar\Attributes\Page;

/**
 * The signed-in account's API tokens (ADR 0159): each one it holds, with
 * where it stands and when it was last used, a form to issue another and a
 * button to revoke each.
 *
 * Nothing here shows a token's text: only its digest is kept. A new one's
 * text is in the answer to the action that issued it, which the form shows
 * once. The scopes offered are those the app's API declares with
 * #[RequiresScope] (ApiTokens::grantable()), described by SCOPES; one the
 * API declares that SCOPES does not describe is offered by its name.
 *
 * A token is held to the account's credential epoch (the accounts kit's
 * $tokenHolders, which the Kernel hands ApiAccess): signing in elsewhere
 * leaves it working, and signing out everywhere, a new password or address,
 * or turning two-factor off ends it.
 */
#[Page('/account/api-tokens', view: 'account/api-tokens', name: 'account.apiTokens'), Middleware(RequireSignIn::class)]
final readonly class ApiTokenSettings
{
    /** The most tokens an account may hold at once (the Kernel's ApiAccess). */
    public const int MAX_TOKENS = 10;

    /** What each scope the app's API declares lets a token do, in the page's words. */
    public const array SCOPES = [
        'notes:read' => 'Read the notes',
    ];

    public function __construct(private ApiTokens $tokens) {}

    public function __invoke(): ApiTokensPageData
    {
        return new ApiTokensPageData(
            tokens: array_map(static function (array $listed): ApiTokenData {
                [$token, $status] = $listed;

                return new ApiTokenData(
                    id: $token->id,
                    name: $token->name,
                    scopes: $token->scopes,
                    status: $status,
                    statusLabel: $status->describe(),
                    createdAt: $token->createdAt,
                    expiresAt: $token->expiresAt,
                    lastUsedAt: $token->lastUsedAt,
                );
            }, $this->tokens->listed()),
            scopes: array_map(
                static fn (string $scope): ApiTokenChoiceData => new ApiTokenChoiceData($scope, self::SCOPES[$scope] ?? $scope),
                $this->tokens->grantable(),
            ),
            lifetimes: array_map(
                static fn (TokenLifetime $lifetime): ApiTokenChoiceData => new ApiTokenChoiceData($lifetime->value, $lifetime->label()),
                TokenLifetime::cases(),
            ),
            maxTokens: self::MAX_TOKENS,
        );
    }
}
