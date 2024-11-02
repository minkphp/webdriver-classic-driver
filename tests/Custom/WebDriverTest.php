<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Mink\WebdriverClassicDriver\Tests\WebdriverClassicConfig;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;
use PHPUnit\Framework\TestCase;

class WebDriverTest extends TestCase
{
    private WebdriverClassicDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = WebdriverClassicConfig::getInstance()->createDriver();
    }

    protected function tearDown(): void
    {
        if ($this->driver->isStarted()) {
            $this->driver->stop();
        }

        parent::tearDown();
    }

    public function testDriverMustBeStartedBeforeUse(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Driver has not been started');

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
        $this->expectExceptionMessage('Driver has already been started');

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
            WebdriverClassicConfig::getInstance()->getBrowserName(),
            $this->driver->getBrowserName()
        );
    }
}
