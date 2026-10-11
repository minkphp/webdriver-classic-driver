<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Mink\WebdriverClassicDriver\Tests\WebdriverClassicConfig;
use Symfony\Component\Process\Process;

$fixturesUrl = WebdriverClassicConfig::getInstance()->getWebFixturesUrl();
$minkTestServerPort = parse_url($fixturesUrl, PHP_URL_PORT) ?: '8002';

$minkTestServer = new Process([
    PHP_BINARY,
    '-S',
    '0.0.0.0:' . $minkTestServerPort,
    '-t',
    __DIR__ . '/../vendor/mink/driver-testsuite/web-fixtures'
]);
// Unread output fills the pipe buffer and blocks the server.
$minkTestServer->disableOutput();
$minkTestServer->start();

register_shutdown_function(
    static fn() => $minkTestServer->stop()
);
