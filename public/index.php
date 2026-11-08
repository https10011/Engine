<?php

declare(strict_types=1);

/**
 * Universal Casino Game Engine — HTTP front controller.
 *
 * This is the only file that should be reachable from the web root. It builds
 * the engine, dispatches the request through the API kernel and sends the
 * response. Everything else — source, configuration, migrations, logs, tests —
 * lives above the web root and must not be servable.
 *
 * Virtual credits only. Nothing here touches real money, and there is no
 * deposit, withdrawal or payment code anywhere in the project.
 */

use CasinoEngine\Api\ApiKernel;
use CasinoEngine\Api\Http\Request;
use CasinoEngine\Api\Http\Response;
use CasinoEngine\Core\Bootstrap;

// Resolve the application root even when the project is deployed with the
// source tree outside the document root.
$root = dirname(__DIR__);

$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => false,
        'data'  => null,
        'error' => ['code' => 'NOT_INSTALLED', 'message' => 'Dependencies are not installed.'],
        'meta'  => (object) [],
    ]);
    exit;
}

require $autoload;

// Under PHP's built-in server this file is also the *router script*, so every
// request — including `/assets/...` — reaches it. A real web server never does
// that (the rewrite in `.htaccess` lets existing files through), so without this
// the stylesheet and module scripts would be answered with the HTML shell and
// `text/html`, and a browser would refuse them under `nosniff`. Returning false
// hands the request back to the built-in server, which serves the file with the
// correct content type.
if (PHP_SAPI === 'cli-server') {
    $assetPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $candidate = realpath(__DIR__ . $assetPath);
    if (
        $assetPath !== '/'
        && $candidate !== false
        && str_starts_with($candidate, __DIR__ . DIRECTORY_SEPARATOR)
        && is_file($candidate)
    ) {
        return false;
    }
}

// Never print a raw PHP error to a client. The engine logs what it needs.
ini_set('display_errors', '0');
error_reporting(E_ALL);

$engine = Bootstrap::engine($root);
$kernel = new ApiKernel($engine);

$request = Request::fromGlobals();

// Anything that is not an API call is the single-page application. The API is
// the only thing that returns JSON; every other path returns the same HTML
// shell, and the client router decides what to show. Static files under
// `assets/` never reach here — the web server serves them, or the rewrite in
// `.htaccess` lets them through — so this branch is only reached for a route.
if (!str_starts_with($request->path, '/api/')) {
    $shell = __DIR__ . '/app.html';
    if (!is_file($shell)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'The application shell is missing.';
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    // The client fetches game data from this origin only, and never loads a
    // script or a style from anywhere else.
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
        . "style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'none'"
    );
    readfile($shell);
    exit;
}

$response = $kernel->handle($request);

if (!$response instanceof Response) {
    $response = Response::error(500, [
        'code'    => 'INTERNAL_ERROR',
        'message' => 'The request could not be completed.',
        'details' => (object) [],
    ]);
}

$response->send();
