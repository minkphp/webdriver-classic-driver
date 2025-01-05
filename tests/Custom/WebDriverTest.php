<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Mink\WebdriverClassicDriver\Tests\WebDriverMockingTrait;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;

class WebDriverTest extends TestCase
{
    use WebDriverMockingTrait;

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
        $mockWebDriver = $this->createMockWebDriver();
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

    public function testThatDriverCatchesUnexpectedAttributeValueType(): void
    {
        $mockWebDriver = $this->createMockWebDriver();
        $mockElement = $this->createMock(RemoteWebElement::class);
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
