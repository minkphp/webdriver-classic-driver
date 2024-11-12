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
    public static function getInstance(): self
    {
        return new self();
    }

    public function createDriver(): WebdriverClassicDriver
    {
        $seleniumHost = $_SERVER['DRIVER_URL'];

        return new WebdriverClassicDriver($this->getBrowserName(), [], $seleniumHost);
    }

    public function getBrowserName(): string
    {
        return getenv('WEB_FIXTURES_BROWSER') ?: WebdriverClassicDriver::DEFAULT_BROWSER;
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
        switch (true) {
            case $testCase === WindowTest::class && $test === 'testWindowMaximize' && $this->isXvfb():
                return 'Maximizing the window does not work when running the browser in Xvfb.';

            case $testCase === BasicAuthTest::class:
                return 'Basic auth is not supported.';

            case $testCase === HeaderTest::class:
                return 'Headers are not supported.';

            case $testCase === StatusCodeTest::class:
                return 'Checking status code is not supported.';

            case $testCase === EventsTest::class && $test === 'testKeyboardEvents' && $this->isOldChrome():
                return 'Old Chrome does not allow triggering events.';

            case $testCase === TimeoutTest::class && $this->getBrowserName() !== 'firefox':
                if ($test === 'testDeprecatedShortPageLoadTimeoutThrowsException' && $this->isXvfb()) {
                    return 'Attempt to set page load timeout several times causes a freeze in this browser.';
                }
                // no break

            case [JavascriptTest::class, 'testDragDropOntoHiddenItself'] === [$testCase, $test]:
                $seleniumVersion = $_SERVER['SELENIUM_VERSION'] ?? null;
                $browser = $_SERVER['WEB_FIXTURES_BROWSER'] ?? null;

                if ($seleniumVersion && version_compare($seleniumVersion, '3.0.0', '<') && $browser === 'firefox') {
                    return 'The Firefox browser compatible with Selenium Server 2.x doesn\'t fully implement drag-n-drop support.';
                }
                // no break

            default:
                return parent::skipMessage($testCase, $test);
        }
    }

    /**
     * {@inheritdoc}
     */
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
        return getenv('WEB_FIXTURES_BROWSER') === 'chrome'
            && version_compare(getenv('SELENIUM_VERSION') ?: '', '3', '<');
    }
}
