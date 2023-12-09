<?php

namespace Mink\WebdriverClassDriver\Tests\Custom;

use Behat\Mink\Tests\Driver\TestCase;
use Mink\WebdriverClassDriver\WebdriverClassicDriver;

class WebDriverTest extends TestCase
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
