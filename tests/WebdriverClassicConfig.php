<?php

namespace Mink\WebdriverClassicDriver\Tests;

use Behat\Mink\Tests\Driver\AbstractConfig;
use Behat\Mink\Tests\Driver\Basic\BasicAuthTest;
use Behat\Mink\Tests\Driver\Basic\HeaderTest;
use Behat\Mink\Tests\Driver\Basic\StatusCodeTest;
use Behat\Mink\Tests\Driver\Js\EventsTest;
use Behat\Mink\Tests\Driver\Js\JavascriptTest;
use Behat\Mink\Tests\Driver\Js\WindowTest;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;

class WebdriverClassicConfig extends AbstractConfig
{
    private int $seleniumMajorVersion;

    public function __construct()
    {
        $this->seleniumMajorVersion = (int) explode('.', $this->getEnvVar('SELENIUM_VERSION') ?? '')[0];
    }

    public static function getInstance(): self
    {
        return new self();
    }

    public function createDriver(): WebdriverClassicDriver
    {
        $seleniumHost = $this->getEnvVar('DRIVER_URL') ?? 'http://localhost:4444/wd/hub';

        return new WebdriverClassicDriver($this->getBrowserName(), [], $seleniumHost);
    }

    public function getBrowserName(): string
    {
        return $this->getEnvVar('WEB_FIXTURES_BROWSER') ?? WebdriverClassicDriver::DEFAULT_BROWSER;
    }

    public function mapRemoteFilePath($file): string
    {
        // Auto-detect "TEST_MACHINE_BASE_PATH" from the test fixtures folder location, so only "DRIVER_MACHINE_BASE_PATH"
        // has to be set, when only this folder is shared with the Selenium machine (e.g. "/fixtures/" in "docker-compose.yml").
        if (null === $this->getEnvVar('TEST_MACHINE_BASE_PATH')) {
            $webFixturesPath = dirname(__DIR__) . '/vendor/mink/driver-testsuite/web-fixtures';
            putenv('TEST_MACHINE_BASE_PATH=' . realpath($webFixturesPath) . DIRECTORY_SEPARATOR);
        }

        return parent::mapRemoteFilePath($file);
    }

    public function skipMessage($testCase, $test): ?string
    {
        switch (true) {
            case [$testCase, $test] === [WindowTest::class, 'testWindowMaximize']
                && $this->isXvfb():
                return 'Maximizing the window does not work when running the browser in Xvfb.';

            case $testCase === BasicAuthTest::class:
                return 'Basic auth is not supported.';

            case $testCase === HeaderTest::class:
                return 'Headers are not supported.';

            case $testCase === StatusCodeTest::class:
                return 'Checking status code is not supported.';

            case [$testCase, $test] === [EventsTest::class, 'testKeyboardEvents']
                && $this->isOldChrome():
                return 'Old Chrome does not allow triggering events.';

            case [$testCase, $test] === [JavascriptTest::class, 'testDragDropOntoHiddenItself']
                && $this->isOldFirefox():
                return 'The Firefox browser compatible with Selenium Server 2.x does not fully implement drag-n-drop support.';

            default:
                return parent::skipMessage($testCase, $test);
        }
    }

    protected function supportsCss(): bool
    {
        return true;
    }

    private function isXvfb(): bool
    {
        return getenv('GITHUB_ACTIONS') === 'true';
    }

    private function isOldChrome(): bool
    {
        return $this->getBrowserName() === 'chrome' && $this->seleniumMajorVersion < 3;
    }

    private function isOldFirefox(): bool
    {
        return $this->getBrowserName() === 'firefox' && $this->seleniumMajorVersion < 3;
    }
}
