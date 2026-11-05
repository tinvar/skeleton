<?php

declare(strict_types=1);

/**
 * Every test starts as a fresh server does, with nothing an earlier test
 * counted or cached. Where the CLI has APCu (`apc.enable_cli=1`), the
 * throttles and the query cache count and keep there (Kernel::throttleStore()
 * and cacheStore()), and APCu is one store for the whole test process: a
 * throttle that counted every test's sign-ups from one address refused the
 * later ones with a 429. tests/Pest.php empties it before each test.
 *
 * The two tests run in this order, in one process: the first counts, the
 * second finds nothing counted.
 */

namespace Tests\Feature\Isolation;

use App\Kernel;
use Tinvar\Http\ApcuThrottleStore;

const KEY = 'isolation-probe';
const WINDOW = 3600;

it('counts a throttle hit and caches an entry, as a request does', function () {
    expect(Kernel::throttleStore(':memory:')->increment(KEY, WINDOW)[0])->toBe(1);
    Kernel::cacheStore()->put(KEY, 'kept', WINDOW);
    expect(Kernel::cacheStore()->get(KEY))->toBe(['hit' => true, 'value' => 'kept']);
})->skip(!ApcuThrottleStore::available(), 'APCu is not loaded in this CLI: each test opens stores of its own.');

it('starts the next test with neither the count nor the entry', function () {
    expect(Kernel::throttleStore(':memory:')->increment(KEY, WINDOW)[0])->toBe(1)
        ->and(Kernel::cacheStore()->get(KEY)['hit'])->toBeFalse();
})->skip(!ApcuThrottleStore::available(), 'APCu is not loaded in this CLI: each test opens stores of its own.');
