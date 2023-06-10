<?php

namespace Mink\WebdriverClassDriver\Tests\Custom;

use Behat\Mink\Tests\Driver\TestCase;

class WindowNameTest extends TestCase
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

    public function testWindowNames(): void
    {
        $windowNames = $this->getSession()->getWindowNames();
        $this->assertArrayHasKey(0, $windowNames);

        $windowName = $this->getSession()->getWindowName();

        $this->assertIsString($windowName);
        $this->assertContains($windowName, $windowNames, 'The current window name is one of the available window names.');
    }
}
