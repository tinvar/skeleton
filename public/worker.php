<?php

declare(strict_types=1);

/**
 * The app as a FrankenPHP worker: booted once per worker thread, then
 * serving request after request until it is recycled.
 *
 * Started by FrankenPHP from the Caddyfile -- by bin/serve, or
 * `bin/tinvar serve` -- with TINVAR_SOCKET naming the renderers.
 */

use App\Kernel;
use Tinvar\Http\Application;
use Tinvar\Http\Worker;

require \dirname(__DIR__) . '/vendor/autoload.php';

// Errors go to the log, never into a response.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$served = new Worker(
    // Once per worker: the routes, registries and policies are built here
    // and kept, and each request's state is cleared by Kernel::reset().
    // Failures go to the app's log under each request's id, and the Worker
    // calls Kernel::terminate() once each response has gone.
    boot: static fn (): Application => Kernel::serving(),
    maxRequests: (int) (getenv('TINVAR_WORKER_MAX_REQUESTS') ?: Worker::DEFAULT_MAX_REQUESTS),
)->run();

// Returning ends this worker: recycled, FrankenPHP starts a fresh one in
// its place; at shutdown, it is simply done.
error_log("[app] worker stopped after {$served} requests");
