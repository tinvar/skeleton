<?php

declare(strict_types=1);

namespace App\Http\Pages\Account;

use App\Http\Data\Account\SecondFactorPageData;
use Tinvar\Accounts\AccountPaths;
use Tinvar\Accounts\RedirectIfSignedIn;
use Tinvar\Accounts\SessionState;
use Tinvar\Attributes\Middleware;
use Tinvar\Attributes\Page;
use Tinvar\Http\Request;

/**
 * The code a sign-in waits on, where two-factor sign-in is on. The session
 * is signed in as nobody until it is typed; one that has waited too long,
 * or never got this far, is told to sign in again.
 */
#[Page('/sign-in/two-factor', view: 'account/two-factor', name: 'account.secondFactor'), Middleware(RedirectIfSignedIn::class)]
final class SecondFactor
{
    public function __construct(private readonly AccountPaths $paths) {}

    public function __invoke(Request $request): SecondFactorPageData
    {
        return new SecondFactorPageData(
            waiting: SessionState::of($request)->pending() !== null,
            next: $this->paths->next($request->query['next'] ?? null),
        );
    }
}
