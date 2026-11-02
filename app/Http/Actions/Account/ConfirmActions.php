<?php

declare(strict_types=1);

namespace App\Http\Actions\Account;

use App\Auth\Account\CurrentUser;
use App\Http\Requests\Account\CurrentPasswordRequest;
use App\Http\Requests\Account\PasskeyConfirmRequest;
use App\Http\Requests\Account\TwoFactorCodeRequest;
use Tinvar\Accounts\Accounts;
use Tinvar\Actions\ActionResult;
use Tinvar\Attributes\Action;
use Tinvar\Http\Request;

/**
 * "Confirm it's you" (ADR 0153): the owner proves it is them on this
 * session -- the password typed again, one of the account's passkeys, or a
 * code from its authenticator app -- before adding a passkey or turning
 * two-factor on, which a signed-in session alone may not do. It counts for
 * five minutes, on this session alone; the account page says until when.
 */
final readonly class ConfirmActions
{
    public function __construct(private Accounts $accounts, private CurrentUser $user) {}

    #[Action(name: 'account.confirmWithPassword')]
    public function confirmWithPassword(CurrentPasswordRequest $request, Request $http): ActionResult
    {
        $this->accounts->confirmWithPassword($http, $this->user->get(), $request->currentPassword);

        return ActionResult::revalidate('account.settings');
    }

    #[Action(name: 'account.confirmWithCode')]
    public function confirmWithCode(TwoFactorCodeRequest $request, Request $http): ActionResult
    {
        $this->accounts->confirmWithCode($http, $this->user->get(), $request->code);

        return ActionResult::revalidate('account.settings');
    }

    /** What navigator.credentials.get() is handed, offering the account's own passkeys. */
    #[Action(name: 'account.confirmPasskeyOptions')]
    public function confirmPasskeyOptions(Request $http): ActionResult
    {
        return ActionResult::ok($this->accounts->passkeyConfirmationOptions($http, $this->user->get()));
    }

    #[Action(name: 'account.confirmWithPasskey')]
    public function confirmWithPasskey(PasskeyConfirmRequest $request, Request $http): ActionResult
    {
        $this->accounts->confirmWithPasskey($http, $this->user->get(), $request->credential);

        return ActionResult::revalidate('account.settings');
    }
}
