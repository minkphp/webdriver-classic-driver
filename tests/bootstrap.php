<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;

$minkTestServer = new Process([
    PHP_BINARY,
    '-S',
    '0.0.0.0:8002',
    '-t',
    __DIR__ . '/../vendor/mink/driver-testsuite/web-fixtures'
]);
$minkTestServer->start();

register_shutdown_function(
    static fn() => $minkTestServer->stop()
);
