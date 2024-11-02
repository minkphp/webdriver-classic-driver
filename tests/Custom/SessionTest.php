<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

class SessionTest extends TestCase
{
    public function testNewDriverShouldNotHaveSessionId(): void
    {
        $driver = $this->driver;

        $this->assertNull($driver->getWebDriverSessionId(), 'Non-started session should not have an ID');
    }

    public function testStartedDriverShouldHaveSessionId(): void
    {
        $driver = $this->driver;
        $driver->start();

        $this->assertNotEmpty($driver->getWebDriverSessionId(), 'Started session should have an ID');
    }
}
