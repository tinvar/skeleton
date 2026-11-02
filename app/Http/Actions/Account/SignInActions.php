<?php

declare(strict_types=1);

namespace App\Http\Actions\Account;

use App\Http\Requests\Account\PasskeySignInRequest;
use App\Http\Requests\Account\SecondFactorRequest;
use App\Http\Requests\Account\SignInRequest;
use Tinvar\Accounts\Accounts;
use Tinvar\Accounts\SignInResult;
use Tinvar\Actions\ActionResult;
use Tinvar\Attributes\Action;
use Tinvar\Attributes\PublicAccess;
use Tinvar\Http\Request;

/**
 * Signing in -- by password, then a code where two-factor sign-in is on, or
 * by passkey -- and out (ADR 0152).
 *
 * Every one of them rotates the session id, so an id a visitor had before
 * -- one an attacker planted or saw -- never carries the change. A wrong
 * password and an unknown email are one message, taking the same time;
 * guesses at either, and at a code, are counted per account and address.
 */
final readonly class SignInActions
{
    public function __construct(private Accounts $accounts) {}

    /**
     * A redirect either way, which both kinds of form follow: on to where the
     * visitor was going, or to the code an account with two-factor asks for.
     */
    #[Action(name: 'account.signIn'), PublicAccess('A visitor signing in is not signed in yet.')]
    public function signIn(SignInRequest $credentials, Request $http): ActionResult
    {
        $paths = $this->accounts->paths();
        $next = $paths->next($credentials->next);
        $result = $this->accounts->signIn($http, $credentials->email, $credentials->password);

        return ActionResult::redirect($result === SignInResult::SecondFactor ? $paths->secondFactor . '?next=' . rawurlencode($next) : $next);
    }

    #[Action(name: 'account.completeSecondFactor'), PublicAccess('Half-way through signing in, nobody is signed in yet.')]
    public function completeSecondFactor(SecondFactorRequest $request, Request $http): ActionResult
    {
        $this->accounts->completeSecondFactor($http, $request->code);

        return ActionResult::redirect($this->accounts->paths()->next($request->next));
    }

    /** The challenge a passkey signs to sign in, bound to this session; handed to navigator.credentials.get(). */
    #[Action(name: 'account.passkeySignInOptions'), PublicAccess('A visitor signing in with a passkey is not signed in yet.')]
    public function passkeySignInOptions(Request $http): ActionResult
    {
        return ActionResult::ok($this->accounts->passkeySignInOptions($http));
    }

    /** Signs in with the passkey's signed answer; the page then goes where the visitor was going. */
    #[Action(name: 'account.signInWithPasskey'), PublicAccess('A visitor signing in with a passkey is not signed in yet.')]
    public function signInWithPasskey(PasskeySignInRequest $request, Request $http): ActionResult
    {
        $this->accounts->signInWithPasskey($http, $request->credential);

        return ActionResult::ok(['next' => $this->accounts->paths()->next($request->next)]);
    }

    #[Action(name: 'account.signOut')]
    public function signOut(Request $http): ActionResult
    {
        $this->accounts->signOut($http);

        return ActionResult::ok(['next' => $this->accounts->paths()->signedOut]);
    }

    /**
     * Signs the account out on every device, this one included: every other
     * session ends, and every token and emailed link minted for it stops
     * counting (Sessions::signOutEverywhere()).
     */
    #[Action(name: 'account.signOutEverywhere')]
    public function signOutEverywhere(Request $http): ActionResult
    {
        $this->accounts->signOutEverywhere($http);

        return ActionResult::ok(['next' => $this->accounts->paths()->signedOut]);
    }
}
