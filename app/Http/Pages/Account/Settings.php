<?php

declare(strict_types=1);

namespace App\Http\Pages\Account;

use App\Auth\Account\CurrentUser;
use App\Http\Data\Account\PasskeyData;
use App\Http\Data\Account\SettingsPageData;
use App\Http\Data\Account\TwoFactorSetupData;
use Tinvar\Accounts\Accounts;
use Tinvar\Accounts\Passkeys\Passkey;
use Tinvar\Accounts\Passwords;
use Tinvar\Accounts\RequireSignIn;
use Tinvar\Attributes\Middleware;
use Tinvar\Attributes\Page;
use Tinvar\Http\Request;

/**
 * The account page: the address and password, passkeys, two-factor sign-in
 * and "sign out everywhere". Signed-in users only (RequireSignIn); anyone
 * else is sent to sign in and brought back.
 */
#[Page('/account', view: 'account/settings', name: 'account.settings'), Middleware(RequireSignIn::class)]
final class Settings
{
    /** What each action's redirect said it did, in the page's words. */
    private const array NOTICES = [
        'password' => 'Your password is changed. Every other device was signed out.',
        'email-sent' => 'We sent a link to the new address. Open it there to move your account; until then, nothing changes.',
        'email' => 'Your account is on its new address now. We told the old one.',
    ];

    public function __construct(private readonly Accounts $accounts, private readonly CurrentUser $user) {}

    public function __invoke(Request $request): SettingsPageData
    {
        $user = $this->user->get();
        $setup = $this->accounts->twoFactorEnabled($user) ? null : $this->accounts->pendingTwoFactor($user);
        $done = $request->query['done'] ?? null;

        return new SettingsPageData(
            name: $user->name,
            email: $user->email,
            passkeys: array_map(
                static fn (Passkey $passkey): PasskeyData => new PasskeyData($passkey->id, $passkey->name, $passkey->backedUp, $passkey->createdAt, $passkey->lastUsedAt),
                $this->accounts->passkeys($user),
            ),
            twoFactorEnabled: $this->accounts->twoFactorEnabled($user),
            recoveryCodesLeft: $this->accounts->recoveryCodesLeft($user),
            twoFactorSetup: $setup === null ? null : new TwoFactorSetupData($setup['secret'], $setup['uri']),
            notice: \is_string($done) ? (self::NOTICES[$done] ?? null) : null,
            minLength: Passwords::MIN_LENGTH,
        );
    }
}
