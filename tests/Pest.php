<?php

declare(strict_types=1);

/*
 * What every test needs before it runs: the testing layer's functions --
 * get(), action(), query(), actingAs() -- and an app key and a probe token
 * of the run's own, so a test never generates either into your home
 * directory.
 */

\Tinvar\Testing\Testing::functions();

if ((string) getenv('TINVAR_APP_KEY') === '') {
    putenv('TINVAR_APP_KEY=' . base64_encode(random_bytes(32)));
}

if ((string) getenv('TINVAR_PROBE_TOKEN') === '') {
    putenv('TINVAR_PROBE_TOKEN=' . bin2hex(random_bytes(24)));
}

/*
 * APCu, where the CLI has it (`apc.enable_cli=1`), is where the throttles
 * count and the query cache keeps (Kernel::throttleStore(), cacheStore()),
 * and it is one store for the whole test process: a count or an entry one
 * test put would reach every test after it -- a throttle counting every
 * test's sign-ups from one address refused the later ones with a 429. Each
 * test starts with it empty, as a fresh server does.
 */
if (\function_exists('apcu_enabled') && apcu_enabled()) {
    pest()->beforeEach(function (): void {
        apcu_clear_cache();
    })->in(__DIR__);
}
