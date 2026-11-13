<?php

declare(strict_types=1);

namespace App\Config;

use Tinvar\Attributes\Config;
use Tinvar\Attributes\Env;
use Tinvar\Config\ConfigException;
use Tinvar\Config\Secret;
use Tinvar\Orm\DatabaseDriver;

/**
 * What the app reads from its environment: each key is a constructor
 * parameter, read from APP_ and its name in upper snake case, or from the
 * variable #[Env] names. Kernel::config() loads it; a key missing or
 * malformed stops the boot with the variable named, and `tinvar
 * config` lists every key and where its value came from.
 *
 * Add a setting here rather than calling getenv(): it is typed, checked at
 * boot, and listed. A secret is typed Tinvar\Config\Secret and marked
 * #[\SensitiveParameter] -- `php tinvar check` refuses one that is not --
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
        // The SQLite database; Kernel::databasePath() has the default. On a
        // server database, the files kept beside it -- the log, the metrics,
        // the throttle counts, the build history -- are still named from it.
        public ?string $database = null,
        // sqlite, postgres or mysql. A server is reached by host, port and
        // name, the user and the password apart: never a URL or a DSN, where
        // the password is one printed line away from a log.
        public DatabaseDriver $databaseDriver = DatabaseDriver::Sqlite,
        public ?string $databaseHost = null,
        public ?int $databasePort = null,
        public ?string $databaseName = null,
        public ?string $databaseUser = null,
        // APP_DATABASE_PASSWORD, or the file APP_DATABASE_PASSWORD_FILE names.
        #[\SensitiveParameter, Env(file: true)]
        public ?Secret $databasePassword = null,
        // Where the JSON log lines go; a file beside the database unless set.
        public ?string $log = null,
        // The https origin the app is served on, as https://app.example:
        // account emails' links are built on it, and passkeys are made for
        // its host. Required where the environment says production; in
        // development the address `php tinvar serve --dev` serves on stands in.
        public ?string $url = null,
        // Where `tinvar serve` and `tinvar serve --dev` say they serve: the stand-in
        // for APP_URL in development. Set by them, not by you.
        #[Env('TINVAR_URL')]
        public ?string $servedAt = null,
        // The address the app's email comes from; required in production.
        public ?string $mailFrom = null,
        // The admin panel's accounts, by address, comma-separated, as
        // `ada@example.com,grace@example.com`. Unset, `php tinvar serve --dev` lets every
        // signed-in account in -- unless a tunnel shares it -- and anywhere else
        // nobody (App\Auth\Admin\Admins).
        public ?string $admins = null,
        // Where mail goes while no TINVAR_MAIL_PROVIDER is named, in
        // development: a file beside the database unless set.
        public ?string $mailLog = null,
        // The renderers' pool directory, or one renderer's socket. Unset, a
        // socket in this user's state directory -- never /tmp, where any
        // local user could put a renderer of their own (ADR 0212).
        #[Env('TINVAR_SOCKET')]
        public ?string $socket = null,
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
    ) {
        // A server named by half is refused at boot, each missing piece by
        // its variable, rather than connecting to a default nobody chose.
        if ($databaseDriver->isServer()) {
            $missing = [];
            foreach (['APP_DATABASE_HOST' => $databaseHost, 'APP_DATABASE_NAME' => $databaseName, 'APP_DATABASE_USER' => $databaseUser] as $variable => $value) {
                if ($value === null || trim($value) === '') {
                    $missing[] = "{$variable} is required where APP_DATABASE_DRIVER is {$databaseDriver->value}.";
                }
            }
            if ($missing !== []) {
                throw new ConfigException($missing);
            }
        }
        if ($databasePort !== null && ($databasePort < 1 || $databasePort > 65535)) {
            throw ConfigException::invalid('APP_DATABASE_PORT', 'must be a port, 1 to 65535');
        }
    }
}
