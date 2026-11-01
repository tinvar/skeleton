<?php

declare(strict_types=1);

namespace App\Http\Pages\Account;

use App\Http\Data\Account\SignUpPageData;
use Tinvar\Accounts\Passwords;
use Tinvar\Accounts\RedirectIfSignedIn;
use Tinvar\Attributes\Middleware;
use Tinvar\Attributes\Page;
use Tinvar\Http\Request;

/**
 * Signing up: an address and a name, then the email. After the form, the
 * same page says to look in the inbox -- whatever the address, since what
 * it says must not tell anyone which addresses have accounts.
 */
#[Page('/sign-up', view: 'account/sign-up', name: 'account.signUp'), Middleware(RedirectIfSignedIn::class)]
final class SignUp
{
    public function __invoke(Request $request): SignUpPageData
    {
        return new SignUpPageData(sent: ($request->query['sent'] ?? null) === '1', minLength: Passwords::MIN_LENGTH);
    }
}
