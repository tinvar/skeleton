<?php

declare(strict_types=1);

namespace App\Http\Actions\Account;

use App\Auth\Account\CurrentUser;
use App\Http\Requests\Account\CurrentPasswordRequest;
use App\Http\Requests\Account\TwoFactorCodeRequest;
use Tinvar\Accounts\Accounts;
use Tinvar\Actions\ActionResult;
use Tinvar\Attributes\Action;
use Tinvar\Http\Request;

/**
 * Two-factor sign-in with an authenticator app (ADR 0153): set up -- a new
 * secret, on only once a code from it is typed -- turned off, and its
 * recovery codes replaced. Setting up needs the session confirmed in the
 * last five minutes (ConfirmActions); every other change asks for the
 * password.
 *
 * The recovery codes come back in the action's answer and are shown once:
 * only their hashes are kept.
 */
final readonly class TwoFactorActions
{
    public function __construct(private Accounts $accounts, private CurrentUser $user) {}

    /** A new secret, shown on the account page until a code from it is typed. */
    #[Action(name: 'account.beginTwoFactor')]
    public function beginTwoFactor(Request $http): ActionResult
    {
        $this->accounts->beginTwoFactor($http, $this->user->get());

        return ActionResult::revalidate('account.settings');
    }

    #[Action(name: 'account.confirmTwoFactor')]
    public function confirmTwoFactor(TwoFactorCodeRequest $request, Request $http): ActionResult
    {
        $codes = $this->accounts->confirmTwoFactor($http, $this->user->get(), $request->code);

        return ActionResult::revalidate('account.settings')->withValue(['recoveryCodes' => $codes]);
    }

    #[Action(name: 'account.disableTwoFactor')]
    public function disableTwoFactor(CurrentPasswordRequest $request, Request $http): ActionResult
    {
        $this->accounts->disableTwoFactor($http, $this->user->get(), $request->currentPassword);

        return ActionResult::revalidate('account.settings');
    }

    #[Action(name: 'account.regenerateRecoveryCodes')]
    public function regenerateRecoveryCodes(CurrentPasswordRequest $request, Request $http): ActionResult
    {
        $codes = $this->accounts->regenerateRecoveryCodes($http, $this->user->get(), $request->currentPassword);

        return ActionResult::revalidate('account.settings')->withValue(['recoveryCodes' => $codes]);
    }
}
