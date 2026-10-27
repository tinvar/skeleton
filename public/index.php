<?php

declare(strict_types=1);

/**
 * The app's front controller, for FrankenPHP's classic mode, which
 * `bin/dev` (`tinvar dev`) runs every request through, and PHP's built-in
 * server. public/worker.php is the same app booted once per FrankenPHP
 * worker, which bin/serve runs.
 */

use App\Kernel;
use Tinvar\Http\NotFoundException;
use Tinvar\Http\Response;
use Tinvar\Http\Sapi;
use Tinvar\Http\Terminable;

require \dirname(__DIR__) . '/vendor/autoload.php';

// Errors go to the log, never into the response.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// php -S serves a file only when the router script says so: the build's
// hashed assets, and nothing else in dist/client.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $root = realpath(Kernel::buildDirectory() . '/client');
    $asset = realpath(Kernel::buildDirectory() . '/client' . (\is_string($path) ? $path : ''));
    $types = ['js' => 'text/javascript', 'css' => 'text/css', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2'];
    $extension = $asset === false ? '' : pathinfo($asset, PATHINFO_EXTENSION);
    if ($asset !== false && $root !== false && str_starts_with($asset, $root . '/assets/') && is_file($asset) && isset($types[$extension])) {
        header('content-type: ' . $types[$extension]);
        header('cache-control: public, max-age=31536000, immutable');
        readfile($asset);

        return true;
    }
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
} catch (NotFoundException) {
    Sapi::send(Response::make(404, 'Not found', ['content-type' => 'text/plain']));

    return;
}

Sapi::send($kernel->handle($request, (string) file_get_contents('php://input')));

// Metrics to their store once the response is over.
if (\function_exists('frankenphp_finish_request')) {
    frankenphp_finish_request();
}
if ($kernel instanceof Terminable) {
    $kernel->terminate();
}
