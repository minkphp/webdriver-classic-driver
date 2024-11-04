<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Mink\WebdriverClassicDriver\Tests\WebdriverClassicConfig;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected WebdriverClassicDriver $driver;

    public function expectDeprecation(string $message = null): void
    {
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = $this->getConfig()->createDriver();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->driver->isStarted()) {
            $this->driver->stop();
        }

        unset($this->driver);
    }

    protected function pathTo(string $path): string
    {
        return rtrim($this->getConfig()->getWebFixturesUrl(), '/') . '/' . ltrim($path, '/');
    }

    protected function getConfig(): WebdriverClassicConfig
    {
        return WebdriverClassicConfig::getInstance();
    }
}
