<?php

declare(strict_types=1);

namespace App\Http\Pages\Account;

use App\Http\Data\Account\ForgotPasswordPageData;
use Tinvar\Attributes\Page;
use Tinvar\Http\Request;

/** Asking for a reset link; after the form, the same page says to look in the inbox. */
#[Page('/password/forgot', view: 'account/forgot-password', name: 'account.forgotPassword')]
final class ForgotPassword
{
    public function __invoke(Request $request): ForgotPasswordPageData
    {
        return new ForgotPasswordPageData(sent: ($request->query['sent'] ?? null) === '1');
    }
}
