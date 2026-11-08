<?php

declare(strict_types=1);

namespace App\Auth\Admin;

use Tinvar\Accounts\AccountUser;
use Tinvar\Accounts\Emails;
use Tinvar\Auth\Authenticatable;

/**
 * Who may open the admin panel: the accounts whose addresses APP_ADMINS
 * lists, comma-separated, as `ada@example.com,grace@example.com`.
 *
 * With APP_ADMINS unset, `php tinvar serve --dev` lets every signed-in account in, so the
 * panel works the moment the app does; dev mode answers on loopback alone
 * and is refused beside any sign of production. Anywhere else -- `tinvar serve`,
 * the container, the tests -- an unset list lets nobody in, and the panel
 * answers 403 until it names someone.
 *
 * The panel's guard (RequireAdmin) and the policies ask here, so a policy
 * that says only an admin may holds wherever its ability is asked, in the
 * panel or out of it.
 */
final readonly class Admins
{
    /** The panel's name, as #[AdminResource(panel:)] and #[Panel] say it. */
    public const string PANEL = 'admin';

    /** @var list<string> each address as accounts are stored: trimmed and lowercased */
    private array $emails;

    /**
     * @param list<string> $emails the admins' addresses, spelled any way
     * @param bool $openInDev whether an empty list lets every signed-in account in: `php tinvar serve --dev` alone
     */
    public function __construct(array $emails, private bool $openInDev = false)
    {
        $this->emails = array_values(array_unique(array_filter(
            array_map(Emails::normalize(...), $emails),
            static fn (string $email): bool => $email !== '',
        )));
    }

    /** APP_ADMINS, as `ada@example.com,grace@example.com`, read as a list. */
    public static function fromList(?string $list, bool $openInDev = false): self
    {
        return new self(explode(',', (string) $list), $openInDev);
    }

    /** Whether $user is one of the app's admins. A guest never is. */
    public function allows(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($this->emails === []) {
            return $this->openInDev;
        }

        return $user instanceof AccountUser
            && \in_array(Emails::normalize($user->accountEmail()), $this->emails, true);
    }

    /** Whether the list names nobody: the panel is then shut, except under `php tinvar serve --dev`. */
    public function unnamed(): bool
    {
        return $this->emails === [];
    }
}
