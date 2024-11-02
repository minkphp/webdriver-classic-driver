<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Tests\Driver\TestCase;
use Mink\WebdriverClassicDriver\Tests\WebdriverClassicConfig;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;

class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->getSession()->start();
    }

    protected function tearDown(): void
    {
        $this->getSession()->stop();

        parent::tearDown();
    }

    public function testGetWebDriverSessionId(): void
    {
        $driver = $this->getSession()->getDriver();
        assert($driver instanceof WebdriverClassicDriver);
        $this->assertNotEmpty($driver->getWebDriverSessionId(), 'Started session should have an ID');

        $driver = new WebdriverClassicDriver();
        $this->assertNull($driver->getWebDriverSessionId(), 'Non-started session should not have an ID');
    }
}
