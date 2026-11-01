<?php

declare(strict_types=1);

namespace App\Http\Pages\Account;

use App\Http\Data\Account\ResetPasswordPageData;
use Tinvar\Accounts\Accounts;
use Tinvar\Accounts\Passwords;
use Tinvar\Accounts\PrivateLinkPage;
use Tinvar\Attributes\Middleware;
use Tinvar\Attributes\Page;
use Tinvar\Http\Request;
use Tinvar\Identity\EmailLinks;

/**
 * Where a reset link lands: the account it is for, and the new password's
 * form. Opening it changes nothing; only the form's POST uses the link.
 */
#[Page('/password/reset', view: 'account/reset-password', name: 'account.resetPassword'), Middleware(PrivateLinkPage::class)]
final class ResetPassword
{
    public function __construct(private readonly Accounts $accounts) {}

    public function __invoke(Request $request): ResetPasswordPageData
    {
        $token = \is_string($request->query[EmailLinks::PARAMETER] ?? null) ? $request->query[EmailLinks::PARAMETER] : '';
        $user = $this->accounts->resetOffer($token);

        return new ResetPasswordPageData(token: $user === null ? '' : $token, email: $user?->accountEmail(), minLength: Passwords::MIN_LENGTH);
    }
}
