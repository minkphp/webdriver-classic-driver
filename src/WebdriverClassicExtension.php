<?php

namespace Mink\WebdriverClassDriver;

use Behat\MinkExtension\ServiceContainer\MinkExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class WebdriverClassicExtension implements Extension
{
    public function getConfigKey(): string
    {
        return 'webdriver-classic';
    }

    public function initialize(ExtensionManager $extensionManager): void
    {
        /** @var MinkExtension $extension */
        $extension = $extensionManager->getExtension(MinkExtension::MINK_ID);
        $extension->registerDriverFactory(new WebDriverClassicFactory());
    }

    public function process(ContainerBuilder $container): void
    {
    }

    public function configure(ArrayNodeDefinition $builder): void
    {
    }

    public function load(ContainerBuilder $container, array $config): void
    {
    }
}
