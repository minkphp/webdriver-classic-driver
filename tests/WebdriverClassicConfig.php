<?php

namespace Mink\WebdriverClassicDriver\Tests;

use Behat\Mink\Tests\Driver\AbstractConfig;
use Behat\Mink\Tests\Driver\Basic\BasicAuthTest;
use Behat\Mink\Tests\Driver\Basic\HeaderTest;
use Behat\Mink\Tests\Driver\Basic\StatusCodeTest;
use Behat\Mink\Tests\Driver\Js\EventsTest;
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

        return new WebdriverClassicDriver(
            $this->getBrowserName(),
            // TODO This is temporary until we have first-class support for switching to headless mode
            $this->isHeaded() ? [] : $this->getHeadlessCapabilities(),
            $seleniumHost
        );
    }

    private function getHeadlessCapabilities(): array
    {
        switch ($browser = getenv('WEB_FIXTURES_BROWSER')) {
            case 'chrome':
                return [
                    'goog:chromeOptions' => [
                        'excludeSwitches' => ['enable-automation'],
                        'args' => [
                            'no-first-run',
                            'no-default-browser-check',
                            'disable-dev-shm-usage',
                            'disable-gpu',
                            'headless=new',
                        ],
                    ],
                ];

            case 'firefox':
                return [
                    'moz:firefoxOptions' => [
                        'args' => [
                            '-headless',
                        ],
                    ],
                ];

            default:
                throw new \RuntimeException("Cannot get headless capabilities of unsupported browser: $browser");
        }
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
            // TODO I believe this may no longer be a problem in headless mode
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
        return $this->isHeaded()
            || filter_var(getenv('GITHUB_ACTIONS'), FILTER_VALIDATE_BOOLEAN);
    }

    private function isHeaded(): bool
    {
        $headed = getenv('HEADED');
        return filter_var($headed !== false ? $headed : 'true', FILTER_VALIDATE_BOOLEAN);
    }

    private function isOldChrome(): bool
    {
        return getenv('WEB_FIXTURES_BROWSER') === 'chrome'
            && version_compare(getenv('SELENIUM_VERSION') ?: '', '3', '<');
    }
}
