<?php

namespace Mink\WebdriverClassDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Tests\Driver\TestCase;
use Mink\WebdriverClassDriver\WebdriverClassicDriver;

class ChromeDesiredCapabilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $driver = $this->getSession()->getDriver();
        if ($driver instanceof WebdriverClassicDriver && $driver->getBrowserName() !== 'chrome') {
            $this->markTestSkipped('This test only applies to Chrome');
        }
    }

    protected function tearDown(): void
    {
        if ($this->getSession()->isStarted()) {
            $this->getSession()->stop();
        }

        parent::tearDown();
    }

    public function testGetDesiredCapabilities(): void
    {
        $caps = [
            'browserName' => 'chrome',
            'version' => '30',
            'platform' => 'ANY',
            'browserVersion' => '30',
            'browser' => 'chrome',
            'name' => 'PhpWebDriver Mink Driver Test',
            'deviceOrientation' => 'portrait',
            'deviceType' => 'tablet',
            'goog:chromeOptions' => [
                'prefs' => [],
            ],
        ];

        $driver = new WebdriverClassicDriver('chrome', $caps);
        $this->assertNotEmpty($driver->getDesiredCapabilities(), 'desiredCapabilities empty');
        $this->assertIsArray($driver->getDesiredCapabilities());
        $this->assertEquals($caps, $driver->getDesiredCapabilities());
    }

    public function testSetDesiredCapabilities(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to set desiredCapabilities, the session has already started');
        $caps = [
            'browserName' => 'chrome',
            'version' => '30',
            'platform' => 'ANY',
            'browserVersion' => '30',
            'browser' => 'chrome',
            'name' => 'PhpWebDriver Mink Driver Test',
            'deviceOrientation' => 'portrait',
            'deviceType' => 'tablet',
        ];
        $session = $this->getSession();
        $session->start();
        $driver = $session->getDriver();
        $driver->setDesiredCapabilities($caps);
    }
}
