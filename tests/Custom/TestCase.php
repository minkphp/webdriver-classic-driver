<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Mink\WebdriverClassicDriver\Tests\WebdriverClassicConfig;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected WebdriverClassicDriver $driver;

    use ExpectDeprecationTrait;

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
    }

    protected function pathTo(string $path): string
    {
        return rtrim($this->getConfig()->getWebFixturesUrl(), '/') . '/' . ltrim($path, '/');
    }

    protected function getConfig(): WebdriverClassicConfig
    {
        return WebdriverClassicConfig::getInstance();
    }

    /**
     * @before
     */
    protected function checkSkippedTest(): void
    {
        $message = self::getConfig()->skipMessage(get_class($this), $this->getName(false));

        if (null !== $message) {
            $this->markTestSkipped($message);
        }
    }
}
