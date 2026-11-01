<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Http\Data\Account\AccountMailData;
use Tinvar\Mail\Address;
use Tinvar\Mail\Email;
use Tinvar\Mail\Mailable;
use Tinvar\Mail\MailCategory;

/**
 * One account email: to whom, its subject, and which template in
 * resources/emails/ draws it from its props. Essential, so it is sent
 * whatever the suppression list holds for an address someone typed a
 * moment ago (ADR 0147); never queued with a link in it (SendAccountMail).
 */
final readonly class AccountEmail implements Mailable
{
    /** @param string $origin the app's origin, which the email's links are on */
    public function __construct(
        public Address $to,
        public string $subject,
        public string $template,
        public AccountMailData $props,
        public string $origin,
    ) {}

    public function email(): Email
    {
        return new Email(
            to: $this->to,
            subject: $this->subject,
            template: $this->template,
            props: $this->props,
            origin: $this->origin,
            category: MailCategory::Essential,
        );
    }
}
