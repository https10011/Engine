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

// Never print a raw PHP error to a client. The engine logs what it needs.
ini_set('display_errors', '0');
error_reporting(E_ALL);

$engine = Bootstrap::engine($root);
$kernel = new ApiKernel($engine);

$request = Request::fromGlobals();
$response = $kernel->handle($request);

if (!$response instanceof Response) {
    $response = Response::error(500, [
        'code'    => 'INTERNAL_ERROR',
        'message' => 'The request could not be completed.',
        'details' => (object) [],
    ]);
}

$response->send();
