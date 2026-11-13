<?php

declare(strict_types=1);

namespace App\Auth\Admin;

use Tinvar\Accounts\AccountUser;
use Tinvar\Accounts\Emails;
use Tinvar\Auth\Authenticatable;
use Tinvar\Dev\Loopback;
use Tinvar\Http\Request;
use Tinvar\Server\PublicHost;

/**
 * Who may open the admin panel: the accounts whose addresses APP_ADMINS
 * lists, comma-separated, as `ada@example.com,grace@example.com`.
 *
 * With APP_ADMINS unset, `php tinvar serve --dev` lets every signed-in
 * account in, so the panel works the moment the app does -- but only to a
 * request from this machine: one naming it (`localhost`, a name under it,
 * a loopback address) from a loopback peer, with no proxy's forwarding
 * headers (reachedLocally()). A tunnel -- `serve --dev --tunnel`, or ngrok
 * or cloudflared started by hand, whose requests arrive from loopback too --
 * names its own host or forwards its visitor's address, and is let in only
 * by APP_ADMINS (ADR 0219). Anywhere else -- `tinvar serve`, the container,
 * the tests -- an unset list lets nobody in, and the panel answers 403
 * until it names someone.
 *
 * The panel's guard (RequireAdmin) and the policies ask here, so a policy
 * that says only an admin may holds wherever its ability is asked, in the
 * panel or out of it.
 */
final readonly class Admins
{
    /** The panel's name, as #[AdminResource(panel:)] and #[Panel] say it. */
    public const string PANEL = 'admin';

    /**
     * The headers a proxy or tunnel adds naming who it forwards for: any of
     * them, and the request did not come from this machine's own browser.
     */
    public const array FORWARDING_HEADERS = [
        'forwarded', 'x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto',
        'x-real-ip', 'cf-connecting-ip', 'true-client-ip',
    ];

    /** @var list<string> each address as accounts are stored: trimmed and lowercased */
    private array $emails;

    /**
     * @param list<string> $emails the admins' addresses, spelled any way
     * @param bool $openInDev whether an empty list lets every signed-in account in: `php tinvar serve --dev` alone
     * @param (\Closure(): bool)|null $reachedLocally whether the request being served came from
     *        this machine (reachedLocally()); with none, an empty list lets nobody in, dev or not
     */
    public function __construct(array $emails, private bool $openInDev = false, private ?\Closure $reachedLocally = null)
    {
        $this->emails = array_values(array_unique(array_filter(
            array_map(Emails::normalize(...), $emails),
            static fn (string $email): bool => $email !== '',
        )));
    }

    /** APP_ADMINS, as `ada@example.com,grace@example.com`, read as a list. */
    public static function fromList(?string $list, bool $openInDev = false, ?\Closure $reachedLocally = null): self
    {
        return new self(explode(',', (string) $list), $openInDev, $reachedLocally);
    }

    /**
     * The admins this process serves: APP_ADMINS, open to every signed-in
     * account in dev while it is unset, for a request $reachedLocally says
     * came from this machine -- and never while `serve --dev` shares dev
     * through its tunnel, which it says in TINVAR_DEV_PUBLIC_HOST.
     *
     * @param array<string, string>|null $env the environment; the process's own by default
     * @param (\Closure(): bool)|null $reachedLocally whether the request being served came from this machine
     */
    public static function forServing(?string $list, bool $dev, ?array $env = null, ?\Closure $reachedLocally = null): self
    {
        $shared = trim((string) (($env ?? array_filter(getenv(), \is_string(...)))[PublicHost::HOST_ENV] ?? ''));

        return self::fromList($list, openInDev: $dev && $shared === '', reachedLocally: $reachedLocally);
    }

    /**
     * Whether $request came from this machine's own browser: from a loopback
     * peer, naming this machine (Loopback::local()), and with none of the
     * headers a proxy or tunnel adds. A tunnel's requests arrive from
     * loopback as well, naming the tunnel's host -- or, told to rewrite it,
     * still forwarding its visitor's address.
     */
    public static function reachedLocally(Request $request): bool
    {
        if (!Loopback::local($request)) {
            return false;
        }
        foreach (self::FORWARDING_HEADERS as $header) {
            if ($request->header($header) !== null) {
                return false;
            }
        }

        return true;
    }

    /** Whether $user is one of the app's admins. A guest never is. */
    public function allows(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($this->emails === []) {
            return $this->openInDev && $this->reachedLocally !== null && ($this->reachedLocally)();
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
