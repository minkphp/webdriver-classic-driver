<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;

$fixturesHost = $_SERVER['WEB_FIXTURES_HOST'] ?? '//host:8002';
if (!is_string($fixturesHost)) {
    throw new RuntimeException('The fixtures host must be specified in $_SERVER[WEB_FIXTURES_HOST] as a string');
}
$minkTestServerPort = parse_url($fixturesHost, PHP_URL_PORT);

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
