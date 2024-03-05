# Mink WebDriver Classic Driver

[![Latest Stable Version](https://poser.pugx.org/mink/webdriver-classic-driver/v)](https://packagist.org/packages/mink/webdriver-classic-driver)
[![Latest Unstable Version](https://poser.pugx.org/mink/webdriver-classic-driver/v/unstable)](https://packagist.org/packages/mink/webdriver-classic-driver)
[![Total Downloads](https://poser.pugx.org/mink/webdriver-classic-driver/downloads)](https://packagist.org/packages/mink/webdriver-classic-driver)
[![CI](https://github.com/minkphp/webdriver-classic-driver/actions/workflows/ci.yml/badge.svg)](https://github.com/minkphp/webdriver-classic-driver/actions/workflows/ci.yml)
[![License](https://poser.pugx.org/mink/webdriver-classic-driver/license)](https://github.com/minkphp/webdriver-classic-driver/blob/main/LICENSE)
[![codecov](https://codecov.io/gh/minkphp/webdriver-classic-driver/branch/main/graph/badge.svg?token=11hgqXqod9)](https://codecov.io/gh/minkphp/webdriver-classic-driver)

## Usage Example

``` php
<?php

use Behat\Mink\Mink,
    Behat\Mink\Session,
    Mink\WebdriverClassicDriver\WebdriverClassicDriver;

require_once __DIR__ . '/vendor/autoload.php';

$browserName = 'firefox';
$url = 'http://example.com';

$mink = new Mink(array(
    'webdriver-classic' => new Session(new WebdriverClassicDriver($browserName)),
));

$session = $mink->getSession('webdriver-classic');
$session->visit($url);

$session->getPage()->findLink('Chat')->click();
```

Please refer to [MinkExtension-example](https://github.com/Behat/MinkExtension-example) for an executable example.

## Installation

``` bash
composer require behat/mink mink/webdriver-classic-driver
```

## Testing

1. Start WebDriver
    1. If you have Docker installed, run
    ```bash
    docker run -p 4444:4444 selenium/standalone-firefox:4.18.1
    ```
    2. If you do not have Docker, but you have Java
    ```bash
    curl -L https://github.com/SeleniumHQ/selenium/releases/download/selenium-4.18.0/selenium-server-4.18.1.jar > selenium-server-4.18.1.jar
    java -jar selenium-server-4.18.1.jar standalone
    ```
2. Start WebServer by running
    ``` bash
    ./vendor/bin/mink-test-server
    ```
3. Start PhpUnit
    ```bash
    ./vendor/bin/phpunit -v --coverage-clover=coverage.clover
    ```
