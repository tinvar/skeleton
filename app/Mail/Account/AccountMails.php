<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Http\Data\Account\AccountMailData;
use App\Kernel;
use Tinvar\Accounts\AccountMails as AccountMailsContract;
use Tinvar\Accounts\AccountUser;
use Tinvar\Mail\Address;
use Tinvar\Mail\Mailable;

/**
 * What the app's account emails say (ADR 0153): the subject and template
 * of each, drawn by resources/emails/ from AccountMailData. The accounts
 * kit decides when each goes and builds every link; change the words here
 * and in the templates.
 */
final readonly class AccountMails implements AccountMailsContract
{
    public const string SIGN_UP = 'account-sign-up';
    public const string ALREADY_REGISTERED = 'account-already-registered';
    public const string PASSWORD_RESET = 'account-password-reset';
    public const string EMAIL_CHANGE = 'account-email-change';
    public const string EMAIL_CHANGED = 'account-email-changed';
    public const string PASSWORD_CHANGED = 'account-password-changed';

    public function signUp(string $email, string $name, string $url, int $minutes): Mailable
    {
        return $this->email(
            new Address($email, self::plain($name)),
            'Finish signing up to ' . Kernel::NAME,
            self::SIGN_UP,
            $url,
            name: $name,
            url: $url,
            minutes: $minutes,
            email: $email,
            reason: "Someone asked for a " . Kernel::NAME . " account for {$email}.",
        );
    }

    public function alreadyRegistered(AccountUser $user, string $signInUrl, string $resetUrl): Mailable
    {
        return $this->email(
            self::to($user),
            'You already have a ' . Kernel::NAME . ' account',
            self::ALREADY_REGISTERED,
            $signInUrl,
            name: $user->accountName(),
            url: $signInUrl,
            secondaryUrl: $resetUrl,
            email: $user->accountEmail(),
            reason: 'Someone tried to sign up to ' . Kernel::NAME . " again as {$user->accountEmail()}.",
        );
    }

    public function passwordReset(AccountUser $user, string $url, int $minutes): Mailable
    {
        return $this->email(
            self::to($user),
            'Reset your ' . Kernel::NAME . ' password',
            self::PASSWORD_RESET,
            $url,
            name: $user->accountName(),
            url: $url,
            minutes: $minutes,
            email: $user->accountEmail(),
            reason: 'Someone asked to reset the password of the ' . Kernel::NAME . " account {$user->accountEmail()}.",
        );
    }

    public function emailChange(AccountUser $user, string $newEmail, string $url, int $minutes): Mailable
    {
        return $this->email(
            new Address($newEmail, self::plain($user->accountName())),
            'Confirm your new ' . Kernel::NAME . ' address',
            self::EMAIL_CHANGE,
            $url,
            name: $user->accountName(),
            url: $url,
            minutes: $minutes,
            email: $newEmail,
            reason: 'Someone asked to move a ' . Kernel::NAME . " account to {$newEmail}.",
        );
    }

    public function emailChanged(AccountUser $user, string $oldEmail, string $resetUrl): Mailable
    {
        return $this->email(
            new Address($oldEmail, self::plain($user->accountName())),
            'Your ' . Kernel::NAME . ' address was changed',
            self::EMAIL_CHANGED,
            $resetUrl,
            name: $user->accountName(),
            url: $resetUrl,
            email: $user->accountEmail(),
            reason: 'The ' . Kernel::NAME . " account that used {$oldEmail} moved to another address.",
        );
    }

    public function passwordChanged(AccountUser $user, string $resetUrl): Mailable
    {
        return $this->email(
            self::to($user),
            'Your ' . Kernel::NAME . ' password was changed',
            self::PASSWORD_CHANGED,
            $resetUrl,
            name: $user->accountName(),
            url: $resetUrl,
            email: $user->accountEmail(),
            reason: 'The password of the ' . Kernel::NAME . " account {$user->accountEmail()} was changed.",
        );
    }

    private function email(Address $to, string $subject, string $template, string $anyUrl, string $name, string $url, string $email, string $reason, string $secondaryUrl = '', int $minutes = 0): AccountEmail
    {
        return new AccountEmail(
            $to,
            $subject,
            $template,
            new AccountMailData(Kernel::NAME, $name, $url, $secondaryUrl, $minutes, $email, $reason),
            // Every link in it is on the app's own origin.
            (string) preg_replace('#^(https?://[^/?\#]+).*\z#', '$1', $anyUrl),
        );
    }

    private static function to(AccountUser $user): Address
    {
        return new Address($user->accountEmail(), self::plain($user->accountName()));
    }

    /** A name as a header may carry it: no line breaks or control characters. */
    private static function plain(string $name): string
    {
        return trim((string) preg_replace('/[\x00-\x1f\x7f]+/', ' ', $name));
    }
}
