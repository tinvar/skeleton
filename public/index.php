<?php

declare(strict_types=1);

/**
 * The app's front controller, for FrankenPHP's classic mode, which
 * `php tinvar serve --dev` runs every request through, and PHP's built-in
 * server. public/worker.php is the same app booted once per FrankenPHP
 * worker, which `tinvar serve` runs.
 */

use App\Kernel;
use Tinvar\Http\BodyTooLarge;
use Tinvar\Http\BuiltAssets;
use Tinvar\Http\NotFoundException;
use Tinvar\Http\Response;
use Tinvar\Http\Sapi;
use Tinvar\Http\Terminable;

require \dirname(__DIR__) . '/vendor/autoload.php';

// Errors go to the log, never into the response.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// The build's assets, when nothing in front of PHP serves them -- php -S, or
// a server without the Caddyfile's file server: only what the build wrote
// under client/assets/, by a hashed name, for a year (BuiltAssets, ADR 0097).
$assets = new BuiltAssets(Kernel::buildDirectory())->match($_SERVER);
if ($assets !== null) {
    Sapi::send($assets);

    return;
}

try {
    // The app key is generated into this user's state directory the first
    // time, unless TINVAR_APP_KEY gives one; a key that cannot be resolved
    // is a failed start like any other.
    $kernel = Kernel::serving();
} catch (\Throwable $e) {
    // Before the Kernel exists there is nothing to classify this: a plain
    // 500, and the cause to the log.
    error_log('[tinvar] could not start: ' . $e::class . ': ' . $e->getMessage());
    Sapi::send(Response::make(500, 'Service unavailable.', ['content-type' => 'text/plain']));

    return;
}

try {
    $request = Sapi::request($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
    // The raw body, never more than Sapi::MAX_BODY_BYTES of it: one that
    // says it is larger is refused before a byte is read.
    $body = Sapi::body($_SERVER);
} catch (NotFoundException) {
    Sapi::send(Response::make(404, 'Not found', ['content-type' => 'text/plain']));

    return;
} catch (BodyTooLarge) {
    Sapi::send(Response::make(413, 'Request body is too large.', ['content-type' => 'text/plain', 'connection' => 'close']));

    return;
}

Sapi::send($kernel->handle($request, $body));

// Metrics to their store once the response is over.
if (\function_exists('frankenphp_finish_request')) {
    frankenphp_finish_request();
}
if ($kernel instanceof Terminable) {
    $kernel->terminate();
}
