<?php

namespace Mink\WebdriverClassDriver\Tests;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Tests\Driver\AbstractConfig;
use Mink\WebdriverClassDriver\WebdriverClassicDriver;

class WebdriverClassicConfig extends AbstractConfig
{
    public static function getInstance()
    {
        return new self();
    }

    public function createDriver(): DriverInterface
    {
        return new WebdriverClassicDriver();
    }

    public function skipMessage($testCase, $test): ?string
    {
        if (true) {
            return 'TODO: implement the initial driver';
        }

        return parent::skipMessage($testCase, $test);
    }

    protected function supportsCss(): bool
    {
        return true;
    }
}
