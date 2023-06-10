<?php

namespace Mink\WebdriverClassDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Tests\Driver\TestCase;

class TimeoutTest extends TestCase
{
    /**
     * @after
     */
    protected function resetSessions(): void
    {
        $session = $this->getSession();

        // Stop the session instead of only resetting it, as timeouts are not reset (they are configuring the session itself)
        if ($session->isStarted()) {
            $session->stop();
        }

        // Reset the array of timeouts to avoid impacting other tests
        $session->getDriver()->setTimeouts([]);

        parent::resetSessions();
    }

    public function testInvalidTimeoutSettingThrowsException(): void
    {
        $this->expectException(DriverException::class);
        $this->getSession()->start();

        $this->getSession()->getDriver()->setTimeouts(['invalid' => 0]);
    }

    public function testShortTimeoutDoesNotWaitForElementToAppear(): void
    {
        $this->getSession()->getDriver()->setTimeouts(['implicit' => 0]);

        $this->getSession()->visit($this->pathTo('/js_test.html'));

        $this->findById('waitable')->click();

        $element = $this->getSession()->getPage()->find('css', '#waitable > div');

        $this->assertNull($element);
    }

    public function testLongTimeoutWaitsForElementToAppear(): void
    {
        $this->getSession()->getDriver()->setTimeouts(['implicit' => 5000]);

        $this->getSession()->visit($this->pathTo('/js_test.html'));
        $this->findById('waitable')->click();
        $element = $this->getSession()->getPage()->find('css', '#waitable > div');

        $this->assertNotNull($element);
    }
}
