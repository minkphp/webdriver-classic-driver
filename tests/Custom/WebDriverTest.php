<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverOptions;
use Facebook\WebDriver\WebDriverTimeouts;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;

class WebDriverTest extends TestCase
{
    public function testDriverMustBeStartedBeforeUse(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Base driver has not been created');

        $this->driver->visit('https://example.com');
    }

    public function testDriverMustBeStartedBeforeItCanBeStopped(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Driver has not been started');

        $this->driver->stop();
    }

    public function testStartedDriverCannotBeSubsequentlyStarted(): void
    {
        $this->driver->start();

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Base driver has already been created');

        $this->driver->start();
    }

    public function testDriverCatchesUpstreamErrorsDuringStart(): void
    {
        $driver = new WebdriverClassicDriver('fake browser', [], 'example.com', function () {
            throw new \RuntimeException('An upstream error');
        });

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Could not start driver: An upstream error');

        $driver->start();
    }

    public function testDriverCatchesUpstreamErrorsDuringStop(): void
    {
        $mockWebDriver = $this->createMock(RemoteWebDriver::class);
        $mockWebDriverOptions = $this->createMock(WebDriverOptions::class);
        $mockWebDriverTimeouts = $this->createMock(WebDriverTimeouts::class);
        $mockWebDriver->method('manage')->willReturn($mockWebDriverOptions);
        $mockWebDriverOptions->method('timeouts')->willReturn($mockWebDriverTimeouts);
        $mockWebDriver->method('quit')->willThrowException(new \RuntimeException('An upstream error'));
        $driver = new WebdriverClassicDriver('fake browser', [], 'example.com', fn() => $mockWebDriver);

        $driver->start();

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Could not close connection: An upstream error');

        $driver->stop();
    }

    public function testClassicDriverCanProvideBrowserName(): void
    {
        $this->assertSame(
            $this->getConfig()->getBrowserName(),
            $this->driver->getBrowserName()
        );
    }
}
