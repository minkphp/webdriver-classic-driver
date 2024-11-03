<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Tests\Driver\TestCase;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;

class TimeoutTest extends TestCase
{
    /**
     * @after
     */
    protected function resetSessions(): void
    {
        $session = $this->getSession();
        $driver = $this->getSession()->getDriver();
        assert($driver instanceof WebdriverClassicDriver);

        // Stop the session instead of only resetting it, as timeouts are not reset (they are configuring the session itself)
        if ($session->isStarted()) {
            $session->stop();
        }

        // Reset the array of timeouts to avoid impacting other tests
        $driver->setTimeouts([]);

        parent::resetSessions();
    }

    public function testInvalidTimeoutSettingThrowsException(): void
    {
        $this->getSession()->start();
        $driver = $this->getSession()->getDriver();
        assert($driver instanceof WebdriverClassicDriver);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Invalid timeout type: invalid');

        $driver->setTimeouts(['invalid' => 0]);
    }

    public function testShortTimeoutDoesNotWaitForElementToAppear(): void
    {
        $driver = $this->getSession()->getDriver();
        assert($driver instanceof WebdriverClassicDriver);
        $driver->setTimeouts(['implicit' => 0]);

        $this->getSession()->visit($this->pathTo('/js_test.html'));
        $this->findById('waitable')->click();
        $element = $this->getSession()->getPage()->find('css', '#waitable > div');

        $this->assertNull($element);
    }

    public function testLongTimeoutWaitsForElementToAppear(): void
    {
        $driver = $this->getSession()->getDriver();
        assert($driver instanceof WebdriverClassicDriver);
        $driver->setTimeouts(['implicit' => 5000]);

        $this->getSession()->visit($this->pathTo('/js_test.html'));
        $this->findById('waitable')->click();
        $element = $this->getSession()->getPage()->find('css', '#waitable > div');

        $this->assertNotNull($element);
    }

    public function testShortPageLoadTimeoutThrowsException(): void
    {
        $session = $this->getSession();
        $driver = $session->getDriver();
        \assert($driver instanceof WebdriverClassicDriver);

        $driver->setTimeouts(['page' => 500]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Page failed to load: ');
        $session->visit($this->pathTo('/page_load.php?sleep=2'));
    }
}
