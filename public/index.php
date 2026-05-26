<?php

// PHP 8.5 deprecates some constants still used by the framework/dependencies
// (e.g. PDO::MYSQL_ATTR_SSL_CA). Hide deprecation notices so they don't leak
// into the HTML output; real errors are still reported.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
