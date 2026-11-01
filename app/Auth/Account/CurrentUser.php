<?php

declare(strict_types=1);

namespace App\Auth\Account;

use App\Database\Models\Account\User;
use Tinvar\Auth\AuthContext;

/**
 * Who is signed in, for the pages and actions only a signed-in user
 * reaches: an action with no #[PublicAccess] is refused to anyone else
 * before its body runs, and a page behind RequireSignIn redirects them.
 */
final readonly class CurrentUser
{
    public function __construct(private AuthContext $auth) {}

    public function get(): User
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            throw new \LogicException('Nobody is signed in here: put the page behind RequireSignIn, and give the action no #[PublicAccess].');
        }

        return $user;
    }
}
