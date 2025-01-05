<?php

namespace Mink\WebdriverClassicDriver\Tests;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverOptions;
use Facebook\WebDriver\WebDriverTimeouts;
use PHPUnit\Framework\MockObject\MockObject;

trait WebDriverMockingTrait
{
    /**
     * @template T
     * @param class-string<T> $class
     * @return T&MockObject
     */
    abstract function createMock(string $class): object;

    /**
     * @return RemoteWebDriver&MockObject
     */
    private function createMockWebDriver(): RemoteWebDriver
    {
        $mockWebDriverTimeouts = $this->createMock(WebDriverTimeouts::class);

        $mockWebDriverOptions = $this->createMock(WebDriverOptions::class);
        $mockWebDriverOptions->method('timeouts')->willReturn($mockWebDriverTimeouts);

        $mockWebDriver = $this->createMock(RemoteWebDriver::class);
        $mockWebDriver->method('manage')->willReturn($mockWebDriverOptions);

        return $mockWebDriver;
    }
}
