<?php

namespace Mink\WebdriverClassicDriver\Tests;

use Mink\WebdriverClassicDriver\WebdriverClassicDriver;

class DriverExposingCapabilities extends WebdriverClassicDriver
{
    public function getDesiredCapabilities(): array
    {
        return parent::getDesiredCapabilities();
    }
}
