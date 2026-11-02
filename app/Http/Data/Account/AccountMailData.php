<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * What an account email is drawn from (App\Mail\Account\AccountMails):
 * whom it greets, the link it carries and how long that lasts, a second
 * link where it has one, the address it is about, and why it was sent.
 */
final readonly class AccountMailData extends Data
{
    public function __construct(
        public string $appName,
        public string $name,
        public string $url,
        public string $secondaryUrl,
        public int $minutes,
        public string $email,
        public string $reason,
        // What a passkey just added is called, for the email that says so.
        public string $passkey = '',
        // What an API token just created is called -- its name, never its text.
        public string $tokenName = '',
    ) {}
}
