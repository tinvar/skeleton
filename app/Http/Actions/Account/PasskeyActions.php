<?php

declare(strict_types=1);

namespace App\Http\Actions\Account;

use App\Auth\Account\CurrentUser;
use App\Http\Requests\Account\PasskeyRequest;
use App\Http\Requests\Account\RemovePasskeyRequest;
use Tinvar\Accounts\Accounts;
use Tinvar\Actions\ActionResult;
use Tinvar\Attributes\Action;
use Tinvar\Http\Request;
use Tinvar\Validation\ValidationException;

/**
 * The account's passkeys (ADR 0150): adding one -- a challenge bound to
 * this session and account, then the browser's answer verified for this
 * host -- and removing one. Signing in with one is SignInActions'.
 */
final readonly class PasskeyActions
{
    public function __construct(private Accounts $accounts, private CurrentUser $user) {}

    /** What navigator.credentials.create() is handed, its challenge good once for five minutes. */
    #[Action(name: 'account.passkeyOptions')]
    public function passkeyOptions(Request $http): ActionResult
    {
        return ActionResult::ok($this->accounts->passkeyRegistrationOptions($http, $this->user->get()));
    }

    #[Action(name: 'account.addPasskey')]
    public function addPasskey(PasskeyRequest $request, Request $http): ActionResult
    {
        $this->accounts->registerPasskey($http, $this->user->get(), $request->credential, $request->name);

        return ActionResult::revalidate('account.settings');
    }

    #[Action(name: 'account.removePasskey')]
    public function removePasskey(RemovePasskeyRequest $request): ActionResult
    {
        if (!$this->accounts->removePasskey($this->user->get(), $request->id)) {
            throw new ValidationException(['id' => ['That passkey is not on your account any more. Reload the page to see the ones that are.']]);
        }

        return ActionResult::revalidate('account.settings');
    }
}
