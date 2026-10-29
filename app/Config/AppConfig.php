<?php

declare(strict_types=1);

namespace App\Config;

use Tinvar\Attributes\Config;
use Tinvar\Attributes\Env;

/**
 * What the app reads from its environment: each key is a constructor
 * parameter, read from APP_ and its name in upper snake case, or from the
 * variable #[Env] names. Kernel::config() loads it; a key missing or
 * malformed stops the boot with the variable named, and `bin/tinvar
 * config` lists every key and where its value came from.
 *
 * Add a setting here rather than calling getenv(): it is typed, checked at
 * boot, and listed. A secret is typed Tinvar\Config\Secret and marked
 * #[\SensitiveParameter] -- `bin/tinvar check` refuses one that is not --
 * and read with reveal() where it is used:
 *
 *     #[\SensitiveParameter, Env(file: true)]
 *     public ?Secret $mailPassword = null,   // APP_MAIL_PASSWORD, or the file APP_MAIL_PASSWORD_FILE names
 *
 * The app key and the probe token are not here: AppKey reads them, and
 * generates each into its file the first time, which no config does.
 */
#[Config(prefix: 'APP_')]
final readonly class AppConfig
{
    public function __construct(
        // The SQLite database; Kernel::databasePath() has the default.
        public ?string $database = null,
        // Where the JSON log lines go; a file beside the database unless set.
        public ?string $log = null,
        // The renderers' pool directory, or one renderer's socket.
        #[Env('TINVAR_SOCKET')]
        public string $socket = '/tmp/tinvar.sock',
        // Throttle limits over the defaults, as `actions=600`.
        #[Env('TINVAR_THROTTLE')]
        public ?string $throttle = null,
        // 1 lets loopback into the probes without the token.
        #[Env('TINVAR_PROBE_TRUST_LOOPBACK')]
        public bool $probeTrustLoopback = false,
        // The build this server runs, or `dev`.
        #[Env('TINVAR_BUILD')]
        public string $build = 'dev',
        #[Env('TINVAR_BUILDS_FILE')]
        public ?string $buildsFile = null,
        // In dev, where the dev server writes what each view may defer and query.
        #[Env('TINVAR_DEFERRED_MANIFEST')]
        public ?string $deferredManifest = null,
    ) {}
}
