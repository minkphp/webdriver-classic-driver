# Mink WebDriver Classic Driver

[![PHP Version](http://poser.pugx.org/mink/webdriver-classic-driver/require/php)](https://packagist.org/packages/mink/webdriver-classic-driver)
[![Latest Stable Version](https://poser.pugx.org/mink/webdriver-classic-driver/v)](https://packagist.org/packages/mink/webdriver-classic-driver)
[![Latest Unstable Version](https://poser.pugx.org/mink/webdriver-classic-driver/v/unstable)](https://packagist.org/packages/mink/webdriver-classic-driver)
[![Total Downloads](https://poser.pugx.org/mink/webdriver-classic-driver/downloads)](https://packagist.org/packages/mink/webdriver-classic-driver)
[![CI](https://github.com/minkphp/webdriver-classic-driver/actions/workflows/ci.yml/badge.svg)](https://github.com/minkphp/webdriver-classic-driver/actions/workflows/ci.yml)
[![License](https://poser.pugx.org/mink/webdriver-classic-driver/license)](https://github.com/minkphp/webdriver-classic-driver/blob/main/LICENSE)
[![codecov](https://codecov.io/gh/minkphp/webdriver-classic-driver/branch/main/graph/badge.svg?token=11hgqXqod9)](https://codecov.io/gh/minkphp/webdriver-classic-driver)

A [php-webdriver](https://github.com/php-webdriver/php-webdriver)-based driver
for [Mink](https://github.com/minkphp/Mink), supporting Selenium 2-4 and tested on Chrome, Firefox and Edge.

## Installation

### For Mink/Standalone Usage

Simply install the package with composer and check the [Standalone Usage Example](#standalone-usage-example) below.

```shell
composer require "behat/mink" "mink/webdriver-classic-driver"
```

### For Behat/BDD Usage

1. Add all the relevant packages - typically this will be for testing your application, so you'd want to use `--dev`.
    ```shell
    composer require --dev "behat/behat" "behat/mink" "friends-of-behat/mink-extension" "mink/webdriver-classic-driver"
    ```
2. Configure Behat to enable the Mink Extension and set it to use this driver.

## Standalone Usage Example

You will need to run a WebDriver server, either using [Selenium](https://www.selenium.dev/) or the browser driver directly.

```php
<?php

use Behat\Mink\Mink,
    Behat\Mink\Session,
    Mink\WebdriverClassDriver\WebdriverClassicDriver;

require_once __DIR__ . '/vendor/autoload.php';

$browserName = 'firefox';
$url = 'https://example.com';

$mink = new Mink([
    'webdriver-classic' => new Session(new WebdriverClassicDriver($browserName)),
]);

$session = $mink->getSession('webdriver-classic');
$session->visit($url);

$session->getPage()->findLink('Chat')->click();
```

Please refer to [MinkExtension-example](https://github.com/Behat/MinkExtension-example) for an executable example.
