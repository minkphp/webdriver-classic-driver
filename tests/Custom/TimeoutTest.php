<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;

class TimeoutTest extends TestCase
{
    public function testInvalidTimeoutSettingThrowsException(): void
    {
        $this->driver->start();

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Invalid timeout type: invalid');

        $this->driver->setTimeouts(['invalid' => 0]);
    }

    public function testShortTimeoutDoesNotWaitForElementToAppear(): void
    {
        $this->driver->start();
        $this->driver->setTimeouts(['implicit' => 0]);

        $this->driver->visit($this->pathTo('/js_test.html'));
        $this->driver->click('//div[@id="waitable"]');

        $this->assertEmpty($this->driver->getText('//div[@id="waitable"]'));
    }

    public function testLongTimeoutWaitsForElementToAppear(): void
    {
        $this->driver->start();
        $this->driver->setTimeouts(['implicit' => 5000]);

        $this->driver->visit($this->pathTo('/js_test.html'));
        $this->driver->click('//div[@id="waitable"]');

        $this->assertNotEmpty($this->driver->getText('//div[@id="waitable"]/div'));
    }

    /*public function testShortPageLoadTimeoutThrowsException(): void
    {
        $this->driver->start();
        $this->driver->setTimeouts(['page' => 500]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Page failed to load: ');

        $this->driver->visit($this->pathTo('/page_load.php?sleep=2'));
    }*/

    /**
     * @group legacy
     */
    public function testDeprecatedShortPageLoadTimeoutThrowsException1(): void
    {
        $this->driver->start();

        $this->expectDeprecation('Using "pageLoad" timeout type is deprecated, please use "page" instead');
        $this->driver->setTimeouts(['pageLoad' => 500]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Page failed to load: ');
        $this->driver->visit($this->pathTo('/page_load.php?sleep=2'));
    }

    /**
     * @group legacy
     */
    public function testDeprecatedShortPageLoadTimeoutThrowsException2(): void
    {
        $this->driver->start();

        $this->expectDeprecation('Using "page load" timeout type is deprecated, please use "page" instead');
        $this->driver->setTimeouts(['page load' => 500]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Page failed to load: ');
        $this->driver->visit($this->pathTo('/page_load.php?sleep=2'));
    }
}
