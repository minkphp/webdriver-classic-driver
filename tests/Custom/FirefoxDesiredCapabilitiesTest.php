<?php

namespace Mink\WebdriverClassDriver\Tests\Custom;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Tests\Driver\TestCase;
use Mink\WebdriverClassDriver\WebdriverClassicDriver;

class FirefoxDesiredCapabilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $driver = $this->getSession()->getDriver();
        if ($driver instanceof WebdriverClassicDriver && $driver->getBrowserName() !== 'firefox') {
            $this->markTestSkipped('This test only applies to Firefox');
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
            'browserName' => 'firefox',
            'version' => '30',
            'platform' => 'ANY',
            'browserVersion' => '30',
            'browser' => 'firefox',
            'name' => 'PhpWebDriver Mink Driver Test',
            'deviceOrientation' => 'portrait',
            'deviceType' => 'tablet',
            'moz:firefoxOptions' => [
                'prefs' => [],
            ],
        ];

        $driver = new WebdriverClassicDriver('firefox', $caps);
        $this->assertNotEmpty($driver->getDesiredCapabilities(), 'desiredCapabilities empty');
        $this->assertIsArray($driver->getDesiredCapabilities());
        $this->assertEquals($caps, $driver->getDesiredCapabilities());
    }

    public function testSetDesiredCapabilities(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to set desiredCapabilities, the session has already started');
        $caps = [
            'browserName' => 'firefox',
            'version' => '30',
            'platform' => 'ANY',
            'browserVersion' => '30',
            'browser' => 'firefox',
            'name' => 'PhpWebDriver Mink Driver Test',
            'deviceOrientation' => 'portrait',
            'deviceType' => 'tablet',
        ];
        $session = $this->getSession();
        $session->start();
        $this->getDriver()->setDesiredCapabilities($caps);
    }

    private function getDriver(): WebdriverClassicDriver
    {
        /** @phpstan-ignore-next-line */
        return $this->getSession()->getDriver();
    }
}
