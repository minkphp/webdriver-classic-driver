<?php

namespace Mink\WebdriverClassDriver\Tests;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Tests\Driver\AbstractConfig;
use Mink\WebdriverClassDriver\WebdriverClassicDriver;

class WebdriverClassicConfig extends AbstractConfig
{
    public static function getInstance(): self
    {
        return new self();
    }

    public function createDriver(): DriverInterface
    {
        return new WebdriverClassicDriver();
    }

    public function skipMessage($testCase, $test): ?string
    {
        /** @phpstan-ignore-next-line */
        if (true) {
            return 'TODO: implement the initial driver';
        }

        /** @phpstan-ignore-next-line */
        return parent::skipMessage($testCase, $test);
    }

    protected function supportsCss(): bool
    {
        return true;
    }
}
