<?php

declare(strict_types=1);

namespace App\Http\Actions\Account;

use App\Http\Requests\Account\CompleteSignUpRequest;
use App\Http\Requests\Account\SignUpRequest;
use Tinvar\Accounts\Accounts;
use Tinvar\Actions\ActionResult;
use Tinvar\Attributes\Action;
use Tinvar\Attributes\PublicAccess;
use Tinvar\Http\Request;

/**
 * Signing up, in two steps (ADR 0151): an address and a name, then -- from
 * the link emailed to that address -- a password, and the account is made.
 *
 * Asking answers the same whatever the address: an address that already
 * has an account gets an email saying so, from the queue worker, and the
 * form says nothing. And no account exists until its owner has shown they
 * read the address, so nobody can make one in someone else's name and
 * wait for them to sign up into it.
 *
 * Public, as signing in is: nobody signing up is signed in. CSRF still
 * applies, so another site cannot have a visitor's browser ask.
 */
final readonly class SignUpActions
{
    public function __construct(private Accounts $accounts) {}

    #[Action(name: 'account.requestSignUp'), PublicAccess('Signing up is how a visitor gets an account.')]
    public function requestSignUp(SignUpRequest $request, Request $http): ActionResult
    {
        $this->accounts->requestSignUp($http, $request->email, $request->name);

        return ActionResult::redirect($this->accounts->paths()->signUp . '?sent=1');
    }

    /** Makes the account its link is for, and signs this browser in as it. */
    #[Action(name: 'account.completeSignUp'), PublicAccess('The link proves the address; nobody is signed in yet.')]
    public function completeSignUp(CompleteSignUpRequest $request, Request $http): ActionResult
    {
        $this->accounts->completeSignUp($http, $request->token, $request->password);

        return ActionResult::redirect($this->accounts->paths()->home);
    }
}
