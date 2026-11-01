<?php

declare(strict_types=1);

namespace App\Http\Pages\Account;

use App\Http\Data\Account\CompleteSignUpPageData;
use Tinvar\Accounts\Accounts;
use Tinvar\Accounts\Passwords;
use Tinvar\Accounts\PrivateLinkPage;
use Tinvar\Attributes\Middleware;
use Tinvar\Attributes\Page;
use Tinvar\Http\Request;
use Tinvar\Identity\EmailLinks;

/**
 * Where a sign-up link lands: it says which address the account is for and
 * asks for a password. Opening it makes nothing -- a mail scanner opens
 * links too -- and only the form's POST uses the link. Sent with no
 * Referer and kept by no cache (PrivateLinkPage).
 */
#[Page('/sign-up/complete', view: 'account/complete-sign-up', name: 'account.completeSignUp'), Middleware(PrivateLinkPage::class)]
final class CompleteSignUp
{
    public function __construct(private readonly Accounts $accounts) {}

    public function __invoke(Request $request): CompleteSignUpPageData
    {
        $token = \is_string($request->query[EmailLinks::PARAMETER] ?? null) ? $request->query[EmailLinks::PARAMETER] : '';
        $offer = $this->accounts->signUpOffer($token);

        return new CompleteSignUpPageData(
            token: $offer === null ? '' : $token,
            email: $offer['email'] ?? null,
            name: $offer['name'] ?? null,
            minLength: Passwords::MIN_LENGTH,
        );
    }
}
