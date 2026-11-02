<?php

declare(strict_types=1);

namespace App\Http\Actions\Account;

use App\Auth\Account\CurrentUser;
use App\Http\Requests\Account\ChangeEmailRequest;
use App\Http\Requests\Account\LinkTokenRequest;
use Tinvar\Accounts\Accounts;
use Tinvar\Actions\ActionResult;
use Tinvar\Attributes\Action;
use Tinvar\Attributes\PublicAccess;
use Tinvar\Http\Request;

/**
 * Moving an account to a new address (ADR 0152): asked for while signed in,
 * with the password, and done from the link sent to the new address -- so
 * nothing changes until that address is shown to be read. The old address
 * is told.
 */
final readonly class EmailActions
{
    public function __construct(private Accounts $accounts, private CurrentUser $user) {}

    #[Action(name: 'account.requestEmailChange')]
    public function requestEmailChange(ChangeEmailRequest $request, Request $http): ActionResult
    {
        $this->accounts->requestEmailChange($http, $this->user->get(), $request->email, $request->currentPassword);

        return ActionResult::redirect($this->accounts->paths()->home . '?done=email-sent');
    }

    /**
     * Public: the link may be opened in a browser that is not signed in --
     * on a phone, from the inbox. The link itself is the account's, bound
     * to its epoch, and proves the new address.
     */
    #[Action(name: 'account.confirmEmailChange'), PublicAccess('The link is bound to the account and proves the new address.')]
    public function confirmEmailChange(LinkTokenRequest $request, Request $http): ActionResult
    {
        $this->accounts->confirmEmailChange($http, $request->token);

        return ActionResult::redirect($this->accounts->paths()->home . '?done=email');
    }
}
