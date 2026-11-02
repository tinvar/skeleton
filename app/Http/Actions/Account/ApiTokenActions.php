<?php

declare(strict_types=1);

namespace App\Http\Actions\Account;

use App\Http\Data\Account\IssuedApiTokenData;
use App\Http\Requests\Account\CreateApiTokenRequest;
use Carbon\CarbonImmutable;
use Tinvar\Actions\ActionResult;
use Tinvar\Api\ApiTokenChoice;
use Tinvar\Api\ApiTokenRefused;
use Tinvar\Api\ApiTokens;
use Tinvar\Attributes\Action;
use Tinvar\Validation\ValidationException;

/**
 * The account's API tokens, from the API tokens page (ADR 0159): issue
 * one, revoke one.
 *
 * No model is named, so no #[Authorize] names one: ApiTokens acts for the
 * user signed in and for nobody else -- it issues a token to them, lists
 * theirs and revokes only theirs. Both are actions, so they run behind the
 * session and its CSRF token; ApiTokens refuses a request a token signed
 * in, so a token cannot make another.
 */
final readonly class ApiTokenActions
{
    public function __construct(private ApiTokens $tokens) {}

    /**
     * Issues a token, and answers with its text: the only time it is shown.
     * The page that asked shows it from this answer; nothing else keeps it.
     */
    #[Action(name: 'account.createApiToken')]
    public function create(CreateApiTokenRequest $request): ActionResult
    {
        $scopes = array_values(array_filter($request->scopes, \is_string(...)));
        if (\count($scopes) !== \count($request->scopes)) {
            throw new ValidationException(['scopes' => ['Choose scopes from the list.']]);
        }

        try {
            $issued = $this->tokens->issue($request->name, $scopes, $request->lifetime->expiresAt(CarbonImmutable::now('UTC')));
        } catch (ApiTokenRefused $e) {
            // ApiTokens' own words, written for the user choosing, by the
            // field of the form they are about.
            $field = match ($e->choice) {
                ApiTokenChoice::Name, ApiTokenChoice::Count => 'name',
                ApiTokenChoice::Scopes => 'scopes',
                ApiTokenChoice::Expiry => 'lifetime',
            };

            throw new ValidationException([$field => [$e->getMessage()]]);
        }

        // The page drawn again, listing it, beside the text this answer carries.
        return ActionResult::revalidate('account.apiTokens')
            ->withValue(new IssuedApiTokenData($issued->token->id, $issued->token->name, $issued->plaintext));
    }

    /** Revokes one of the account's tokens, at once, on every server. */
    #[Action(name: 'account.revokeApiToken')]
    public function revoke(string $id): ActionResult
    {
        if (!$this->tokens->revoke($id)) {
            throw new ValidationException(['id' => ['That token is not one of yours, or it was revoked already.']]);
        }

        return ActionResult::revalidate('account.apiTokens');
    }
}
