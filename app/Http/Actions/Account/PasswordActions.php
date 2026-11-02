<?php

declare(strict_types=1);

namespace App\Http\Actions\Account;

use App\Auth\Account\CurrentUser;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\EmailRequest;
use App\Http\Requests\Account\ResetPasswordRequest;
use Tinvar\Accounts\Accounts;
use Tinvar\Accounts\SignInResult;
use Tinvar\Actions\ActionResult;
use Tinvar\Attributes\Action;
use Tinvar\Attributes\PublicAccess;
use Tinvar\Http\Request;

/**
 * A forgotten password, reset by an emailed link, and a known one changed
 * (ADR 0151). Either ends every other session the account has and moves its
 * auth epoch, so every other device is signed out and every token and link
 * minted before stops counting; the owner is told by email.
 */
final readonly class PasswordActions
{
    public function __construct(private Accounts $accounts, private CurrentUser $user) {}

    /** The same answer whatever the address, after the same work. */
    #[Action(name: 'account.requestPasswordReset'), PublicAccess('Someone who forgot their password cannot sign in to ask.')]
    public function requestPasswordReset(EmailRequest $request, Request $http): ActionResult
    {
        $this->accounts->requestPasswordReset($http, $request->email);

        return ActionResult::redirect($this->accounts->paths()->forgotPassword . '?sent=1');
    }

    /**
     * Sets the password from the page the link landed on; then the browser
     * is signed in, or -- with two-factor on, since a reset proves only the
     * inbox -- asked for its code.
     */
    #[Action(name: 'account.resetPassword'), PublicAccess('The link proves the inbox; nobody is signed in yet.')]
    public function resetPassword(ResetPasswordRequest $request, Request $http): ActionResult
    {
        $paths = $this->accounts->paths();
        $result = $this->accounts->resetPassword($http, $request->token, $request->password);

        return ActionResult::redirect($result === SignInResult::SecondFactor ? $paths->secondFactor : $paths->home);
    }

    #[Action(name: 'account.changePassword')]
    public function changePassword(ChangePasswordRequest $request, Request $http): ActionResult
    {
        $this->accounts->changePassword($http, $this->user->get(), $request->currentPassword, $request->password);

        return ActionResult::redirect($this->accounts->paths()->home . '?done=password');
    }
}
