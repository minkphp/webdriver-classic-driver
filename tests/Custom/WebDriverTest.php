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
        $this->assertNotEmpty($driver->getWebDriverSessionId(), 'Started session has an ID');

        $driver = new WebdriverClassicDriver();
        $this->assertNull($driver->getWebDriverSessionId(), 'Not started session don\'t have an ID');
    }
}
