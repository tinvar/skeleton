<?php

declare(strict_types=1);

/**
 * Who may read /_health, /_ready and /_metrics: the probe token's holder,
 * and no one for where they connect from. Loopback is no proof of being a
 * probe -- a proxy, a TLS terminator or the renderers on the same machine
 * all connect from it -- so it is trusted only when TINVAR_PROBE_TRUST_LOOPBACK
 * says 1. The token is TINVAR_PROBE_TOKEN, or the one `php tinvar setup`
 * generated beside the app key.
 */

namespace Tests\Feature;

use App\Kernel;
use Tinvar\Http\Request;

function probe(?string $token = null): Request
{
    return new Request('GET', '/_health', $token === null ? [] : ['authorization' => "Bearer {$token}"], remoteAddress: '127.0.0.1');
}

/** Runs $body with these variables set (null unsets one), and puts them back. */
function withEnvironment(array $variables, \Closure $body): mixed
{
    $before = [];
    foreach ($variables as $name => $value) {
        $before[$name] = getenv($name);
        putenv($value === null ? $name : "{$name}={$value}");
    }
    try {
        return $body();
    } finally {
        foreach ($before as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }
}

it('lets in the probe token alone, not a caller for being on loopback', function () {
    $token = (string) getenv('TINVAR_PROBE_TOKEN');
    $access = withEnvironment(['TINVAR_PROBE_TRUST_LOOPBACK' => null], Kernel::probeAccess(...));

    expect($access->allows(probe()))->toBeFalse()
        ->and($access->allows(probe('not-the-token-but-long-enough-to-be-one')))->toBeFalse()
        ->and($access->allows(probe($token)))->toBeTrue();
});

it('trusts loopback only when told to', function () {
    $access = withEnvironment(['TINVAR_PROBE_TRUST_LOOPBACK' => '1'], Kernel::probeAccess(...));

    expect($access->allows(probe()))->toBeTrue();
});

it('generates a probe token beside the app key when none is given, and keeps it', function () {
    $state = sys_get_temp_dir() . '/tinvar-probe-' . bin2hex(random_bytes(4));
    mkdir($state, 0o700);
    try {
        [$first, $again, $file] = withEnvironment(['TINVAR_PROBE_TOKEN' => null, 'TINVAR_PROBE_TOKEN_FILE' => null, 'XDG_STATE_HOME' => $state], fn (): array => [
            Kernel::probeToken(),
            Kernel::probeToken(),
            Kernel::probeTokenFile(),
        ]);

        expect(\strlen($first))->toBeGreaterThanOrEqual(32)
            ->and($again)->toBe($first)
            ->and($file)->toStartWith("{$state}/tinvar/")
            ->and(fileperms($file) & 0o077)->toBe(0);
    } finally {
        exec('rm -rf ' . escapeshellarg($state));
    }
});
