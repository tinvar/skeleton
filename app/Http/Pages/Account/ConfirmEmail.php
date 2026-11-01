<?php

declare(strict_types=1);

namespace App\Http\Pages\Account;

use App\Http\Data\Account\ConfirmEmailPageData;
use Tinvar\Accounts\Accounts;
use Tinvar\Accounts\PrivateLinkPage;
use Tinvar\Attributes\Middleware;
use Tinvar\Attributes\Page;
use Tinvar\Http\Request;
use Tinvar\Identity\EmailLinks;

/**
 * Where a change-of-address link lands: which address the account moves
 * from and to, and a button that moves it. Opening it moves nothing.
 */
#[Page('/account/email/confirm', view: 'account/confirm-email', name: 'account.confirmEmail'), Middleware(PrivateLinkPage::class)]
final class ConfirmEmail
{
    public function __construct(private readonly Accounts $accounts) {}

    public function __invoke(Request $request): ConfirmEmailPageData
    {
        $token = \is_string($request->query[EmailLinks::PARAMETER] ?? null) ? $request->query[EmailLinks::PARAMETER] : '';
        $offer = $this->accounts->emailChangeOffer($token);

        return new ConfirmEmailPageData(
            token: $offer === null ? '' : $token,
            email: $offer === null ? null : $offer['user']->accountEmail(),
            newEmail: $offer['email'] ?? null,
        );
    }
}
