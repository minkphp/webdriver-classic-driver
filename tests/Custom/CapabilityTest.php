<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverOptions;
use Facebook\WebDriver\WebDriverTimeouts;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;

class CapabilityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param array<string, mixed> $desiredCapabilities
     * @param array<string, mixed> $expectedCapabilities
     *
     * @dataProvider capabilitiesDataProvider
     */
    public function testThatCapabilitiesAreAsExpected(string $browserName, array $desiredCapabilities, array $expectedCapabilities): void
    {
        $mockWebDriver = $this->createMock(RemoteWebDriver::class);
        $mockWebDriverOptions = $this->createMock(WebDriverOptions::class);
        $mockWebDriverTimeouts = $this->createMock(WebDriverTimeouts::class);
        $mockWebDriver->method('manage')->willReturn($mockWebDriverOptions);
        $mockWebDriverOptions->method('timeouts')->willReturn($mockWebDriverTimeouts);

        $actualCapabilities = null;
        $driver = new WebdriverClassicDriver(
            $browserName,
            $desiredCapabilities,
            'example.com',
            function ($host, $capabilities) use (&$actualCapabilities, $mockWebDriver) {
                $actualCapabilities = $capabilities->toArray();
                return $mockWebDriver;
            }
        );

        $driver->start();

        $this->assertSame($expectedCapabilities, $actualCapabilities);
    }

    public static function capabilitiesDataProvider(): iterable
    {
        yield 'unknown browser starts with default driver capabilities' => [
            'browserName' => 'fake browser',
            'desiredCapabilities' => [],
            'expectedCapabilities' => [
                'platform' => 'ANY',
                'name' => 'Behat Test',
                'deviceOrientation' => 'landscape',
                'deviceType' => 'desktop',
            ],
        ];

        yield 'default capabilities can be customised' => [
            'browserName' => 'fake browser',
            'desiredCapabilities' => [
                'something' => 'custom',
                'name' => 'Custom Test',
            ],
            'expectedCapabilities' => [
                'platform' => 'ANY',
                'name' => 'Custom Test',
                'deviceOrientation' => 'landscape',
                'deviceType' => 'desktop',
                'something' => 'custom',
            ],
        ];

        yield 'browser-specific default capabilities are added' => [
            'browserName' => 'chrome',
            'desiredCapabilities' => [],
            'expectedCapabilities' => [
                'browserName' => 'chrome',
                'platform' => 'ANY',
                'name' => 'Behat Test',
                'deviceOrientation' => 'landscape',
                'deviceType' => 'desktop',
                'goog:chromeOptions' => [
                    'excludeSwitches' => ['enable-automation'],
                ],
            ],
        ];

        yield 'browser-specific default capabilities can be customised' => [
            'browserName' => 'chrome',
            'desiredCapabilities' => [
                'name' => 'Custom Test',
                'goog:chromeOptions' => ['args' => ['a', 'b', 'c']],
            ],
            'expectedCapabilities' => [
                'browserName' => 'chrome',
                'platform' => 'ANY',
                'name' => 'Custom Test',
                'deviceOrientation' => 'landscape',
                'deviceType' => 'desktop',
                'goog:chromeOptions' => ['args' => ['a', 'b', 'c']],
            ],
        ];
    }
}
