<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

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
        $driver = $this->createDriverExposingCapabilities($browserName, $desiredCapabilities);

        $this->assertSame($expectedCapabilities, $driver->capabilities);
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

    /**
     * @param string $browserName
     * @param array<string, mixed> $desiredCapabilities
     * @return WebdriverClassicDriver&object{capabilities: array<string, mixed>}
     */
    private function createDriverExposingCapabilities(string $browserName, array $desiredCapabilities = []): WebdriverClassicDriver
    {
        return new class($browserName, $desiredCapabilities) extends WebdriverClassicDriver {
            public ?array $capabilities = null;

            public function __construct(string $browserName, array $desiredCapabilities)
            {
                parent::__construct($browserName, $desiredCapabilities);

                $this->capabilities = $this->getDesiredCapabilities();
            }
        };
    }
}
