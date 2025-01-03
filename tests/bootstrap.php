<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;

$fixturesHost = $_SERVER['WEB_FIXTURES_HOST'] ?? '';
$minkTestServerPort = parse_url(is_string($fixturesHost) ? $fixturesHost : '', PHP_URL_PORT) ?: '8002';

$minkTestServer = new Process([
    PHP_BINARY,
    '-S',
    '0.0.0.0:' . $minkTestServerPort,
    '-t',
    __DIR__ . '/../vendor/mink/driver-testsuite/web-fixtures'
]);
$minkTestServer->start();

register_shutdown_function(
    static fn() => $minkTestServer->stop()
);
