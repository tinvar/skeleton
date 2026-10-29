<?php

declare(strict_types=1);

/**
 * The app's settings, through App\Config\AppConfig: declared as
 * `bin/tinvar check` holds them, and a value that is not what it should be
 * refused, by its variable's name, rather than read as something near it.
 */

namespace Tests\Feature;

use App\Config\AppConfig;
use App\Kernel;
use Tinvar\Config\ConfigException;
use Tinvar\Config\Configs;

it('declares its config as bin/tinvar check holds it', function () {
    expect(Configs::check(Kernel::CONFIGS))->toBe([]);
});

it('reads its settings typed, with the defaults where unset', function () {
    $config = Configs::load(AppConfig::class, ['APP_DATABASE' => '/srv/app.sqlite', 'TINVAR_PROBE_TRUST_LOOPBACK' => '1']);

    expect($config->database)->toBe('/srv/app.sqlite')
        ->and($config->probeTrustLoopback)->toBeTrue()
        ->and($config->build)->toBe('dev')
        ->and($config->socket)->toBe('/tmp/tinvar.sock');
});

it('refuses a switch that is not 1 or 0, by name', function () {
    expect(fn () => Configs::load(AppConfig::class, ['TINVAR_PROBE_TRUST_LOOPBACK' => 'true']))
        ->toThrow(ConfigException::class, 'TINVAR_PROBE_TRUST_LOOPBACK must be 1 or 0');
});
