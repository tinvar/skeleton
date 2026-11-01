<?php

declare(strict_types=1);

namespace App\Http\Pages;

use App\Http\Data\Account\LayoutData;
use Tinvar\Attributes\Layout as LayoutAttribute;
use Tinvar\Auth\AuthContext;
use Tinvar\Accounts\AccountUser;

/**
 * The shell around every page: the header and the footer
 * (resources/pages/app/layout.tsx). At `/`, so it wraps every route. Its
 * loader says who is signed in, for the header's account link; it has no
 * model input, so nothing for an #[Authorize] to name: each page under it
 * declares its own.
 */
#[LayoutAttribute('/', view: 'app/layout')]
final class Layout
{
    public function __construct(private readonly AuthContext $auth) {}

    public function __invoke(): LayoutData
    {
        $user = $this->auth->user();

        return new LayoutData($user instanceof AccountUser ? ($user->accountName() === '' ? $user->accountEmail() : $user->accountName()) : null);
    }
}
