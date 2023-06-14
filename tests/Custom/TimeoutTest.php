<?php

namespace Mink\WebdriverClassDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Tests\Driver\TestCase;
use Mink\WebdriverClassDriver\WebdriverClassicDriver;

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
        $this->getDriver()->setTimeouts([]);

        parent::resetSessions();
    }

    public function testInvalidTimeoutSettingThrowsException(): void
    {
        $this->getSession()->start();
        $driver = $this->getDriver();

        $this->expectException(DriverException::class);

        $driver->setTimeouts(['invalid' => 0]);
    }

    public function testShortTimeoutDoesNotWaitForElementToAppear(): void
    {
        $this->getDriver()->setTimeouts(['implicit' => 0]);

        $this->getSession()->visit($this->pathTo('/js_test.html'));

        $this->findById('waitable')->click();

        $element = $this->getSession()->getPage()->find('css', '#waitable > div');

        $this->assertNull($element);
    }

    public function testLongTimeoutWaitsForElementToAppear(): void
    {
        $this->getDriver()->setTimeouts(['implicit' => 5000]);

        $this->getSession()->visit($this->pathTo('/js_test.html'));
        $this->findById('waitable')->click();
        $element = $this->getSession()->getPage()->find('css', '#waitable > div');

        $this->assertNotNull($element);
    }

    private function getDriver(): WebdriverClassicDriver
    {
        /** @phpstan-ignore-next-line */
        return $this->getSession()->getDriver();
    }
}
