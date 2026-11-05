<?php

declare(strict_types=1);

/**
 * Every test starts as a fresh server does, with nothing an earlier test
 * counted or cached. Where the CLI has APCu (`apc.enable_cli=1`), the
 * request budgets and the query cache count and keep there
 * (Kernel::throttleStore() and cacheStore()), and APCu is one store for the
 * whole test process: a budget that counted every test's requests from one
 * address refused the later ones with a 429. tests/Pest.php empties it
 * before each test.
 *
 * Both tests find nothing counted or cached and leave both behind, so
 * whichever runs second fails if anything carried over, in either order.
 */

namespace Tests\Feature\Isolation;

use App\Kernel;
use Tinvar\Http\ApcuThrottleStore;

const KEY = 'isolation-probe';
const WINDOW = 3600;

/** Finds nothing an earlier test counted or cached, then leaves both behind for the next. */
function startsEmptyAndLeavesBoth(): void
{
    expect(Kernel::throttleStore(':memory:')->increment(KEY, WINDOW)[0])->toBe(1)
        ->and(Kernel::cacheStore()->get(KEY)['hit'])->toBeFalse();

    Kernel::cacheStore()->put(KEY, 'kept', WINDOW);
    expect(Kernel::cacheStore()->get(KEY))->toBe(['hit' => true, 'value' => 'kept']);
}

it('starts one test with nothing counted or cached', function () {
    startsEmptyAndLeavesBoth();
})->skip(!ApcuThrottleStore::available(), 'APCu is not loaded in this CLI: each test opens stores of its own.');

it('starts the next with nothing the last one left', function () {
    startsEmptyAndLeavesBoth();
})->skip(!ApcuThrottleStore::available(), 'APCu is not loaded in this CLI: each test opens stores of its own.');
