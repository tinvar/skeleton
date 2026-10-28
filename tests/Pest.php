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
