<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
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
        $driver = $this->createPartialMock(WebdriverClassicDriver::class, ['createWebDriver', 'getWebDriver']);
        $driver
            ->expects($this->once())
            ->method('createWebDriver')
            ->willThrowException(new \RuntimeException('An upstream error'));

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Could not start driver: An upstream error');

        $driver->start();
    }

    public function testDriverCatchesUpstreamErrorsDuringStop(): void
    {
        $driver = $this->createPartialMock(WebdriverClassicDriver::class, ['createWebDriver', 'isStarted', 'getWebDriver']);
        $driver
            ->expects($this->once())
            ->method('isStarted')
            ->willReturn(true);
        $driver
            ->expects($this->once())
            ->method('getWebDriver')
            ->willThrowException(new \RuntimeException('An upstream error'));

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

    public function testThatDriverCatchesUnexpectedAttributeValueType(): void
    {
        $mockWebDriver = $this->createMock(RemoteWebDriver::class);
        $mockWebDriverOptions = $this->createMock(WebDriverOptions::class);
        $mockWebDriverTimeouts = $this->createMock(WebDriverTimeouts::class);
        $mockElement = $this->createMock(RemoteWebElement::class);
        $mockWebDriver->method('manage')->willReturn($mockWebDriverOptions);
        $mockWebDriverOptions->method('timeouts')->willReturn($mockWebDriverTimeouts);
        $mockWebDriver
            ->expects($this->once())
            ->method('findElement')
            ->willReturn($mockElement);
        $mockWebDriver
            ->expects($this->once())
            ->method('executeScript')
            ->with('return arguments[0].getAttribute("some-attribute")', [$mockElement])
            ->willReturn(['invalid attribute value']);

        $driver = new WebdriverClassicDriver('fake browser', [], 'example.com', fn() => $mockWebDriver);

        $driver->start();

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('The element\'s some-attribute attribute should be a string or at least a scalar value, but received `array` instead');

        $driver->getAttribute('//fake', 'some-attribute');
    }
}
