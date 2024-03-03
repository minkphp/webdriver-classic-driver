<?php

namespace Mink\WebdriverClassDriver;

use Behat\MinkExtension\ServiceContainer\Driver\Selenium2Factory;
use Symfony\Component\DependencyInjection\Definition;

class WebdriverClassicFactory extends Selenium2Factory
{
    public function getDriverName(): string
    {
        return 'webdriver-classic';
    }

    public function buildDriver(array $config): Definition
    {
        return new Definition(WebdriverClassicDriver::class, [
            $config['browser'],
            $config['capabilities'],
            $config['wd_host'],
        ]);
    }
}
