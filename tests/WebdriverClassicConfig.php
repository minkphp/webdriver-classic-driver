<?php

namespace Mink\WebdriverClassDriver\Tests;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Tests\Driver\AbstractConfig;
use Behat\Mink\Tests\Driver\Basic\HeaderTest;
use Behat\Mink\Tests\Driver\Basic\StatusCodeTest;
use Behat\Mink\Tests\Driver\Js\WindowTest;
use Mink\WebdriverClassDriver\WebdriverClassicDriver;

class WebdriverClassicConfig extends AbstractConfig
{
    public static function getInstance(): self
    {
        return new self();
    }

    /**
     * {@inheritdoc}
     */
    public function createDriver(): DriverInterface
    {
        $browser = getenv('WEB_FIXTURES_BROWSER') ?: null;
        $seleniumHost = $_SERVER['DRIVER_URL'];

        return new WebdriverClassicDriver($browser, null, $seleniumHost);
    }

    public function mapRemoteFilePath($file): string
    {
        if (!isset($_SERVER['TEST_MACHINE_BASE_PATH'])) {
            $_SERVER['TEST_MACHINE_BASE_PATH'] = realpath(
                    dirname(__DIR__) . '/vendor/mink/driver-testsuite/web-fixtures'
                ) . DIRECTORY_SEPARATOR;
        }

        return parent::mapRemoteFilePath($file);
    }

    public function skipMessage($testCase, $test): ?string
    {
        if (
            $testCase === WindowTest::class
            && $test === 'testWindowMaximize'
            && getenv('GITHUB_ACTIONS') === 'true'
        ) {
            return 'Maximizing the window does not work when running the browser in Xvfb.';
        }

        if ($testCase === HeaderTest::class) {
            return 'Headers are not supported.';
        }

        if ($testCase === StatusCodeTest::class) {
            return 'Checking status code is not supported.';
        }

        // TODO skip event tests for old chrome

        return parent::skipMessage($testCase, $test);
    }

    /**
     * {@inheritdoc}
     */
    protected function supportsCss(): bool
    {
        return true;
    }
}
