<?php

declare(strict_types=1);

namespace App\Http\Pages\Account;

use App\Http\Data\Account\SignInPageData;
use Tinvar\Accounts\AccountPaths;
use Tinvar\Accounts\RedirectIfSignedIn;
use Tinvar\Attributes\Middleware;
use Tinvar\Attributes\Page;
use Tinvar\Http\Request;

/**
 * Signing in, by password or by passkey. `next` -- where the visitor was
 * going -- is held to a path on this site before it is handed on
 * (AccountPaths::next()), so the page is no open redirect.
 */
#[Page('/sign-in', view: 'account/sign-in', name: 'account.signIn'), Middleware(RedirectIfSignedIn::class)]
final class SignIn
{
    public function __construct(private readonly AccountPaths $paths) {}

    public function __invoke(Request $request): SignInPageData
    {
        return new SignInPageData(next: $this->paths->next($request->query['next'] ?? null));
    }
}
