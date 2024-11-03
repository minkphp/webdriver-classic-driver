# Contributing to Mink WebDriver Classic Driver

At the moment, contributions can be in the form of:

1. [Reporting Bugs or Requesting Features](https://github.com/minkphp/webdriver-classic-driver/issues)
2. [Contributing Code (as Pull Requests)](https://github.com/minkphp/webdriver-classic-driver/pulls)

## Licensing

The changes you contribute (as Pull Requests) will be under the same
[MIT License](https://github.com/minkphp/webdriver-classic-driver/blob/main/LICENSE) used by this project. Needless to say, it is your responsibility that your contribution is either
your original work or that it has been allowed by the original author.

## Code Quality

We use automated tools to have a general and consistent level of code quality. Simply run the following before pushing
each commit (or at least before sending a Pull Request):

```shell
composer run lint
```

## Testing

You will need [Docker](https://www.docker.com/products/docker-desktop/), [PHP 7.4](https://php.net/downloads) or higher
and [Composer](https://getcomposer.org) running on your machine.

1. Run the Selenium service (containing Chrome by default) with:
    ```shell
    docker compose up
    ```
2. Secondly, simply run the tests with:
    ```shell
    composer run test
    ```
3. You can observe and interact with the tests by visiting [`localhost:7900`](http://localhost:7900).

### Additional Notes

- By default, Chrome and Selenium 4 is used - but you can change that with the `SELENIUM_IMAGE` env var, e.g.:
    ```shell
    export SELENIUM_IMAGE=selenium/standalone-firefox:3    # Firefox and Selenium 3
    docker compose up
    ```
- Tests depends on a server serving the test fixtures. `bootstrap.php` conveniently starts (and stops) such a server
  for you while tests are running. It defaults to port `8002` for historical reasons, but can be changed with a custom
  PHPUnit config file and changing the `WEB_FIXTURES_HOST` variable (see also the next point).
- To customise any PHPUnit settings, make a copy of `phpunit.xml.dist` named as `phpunit.xml` and change it as needed.
- The test setup can also work without Docker, but takes more effort (downloading and running **Selenium**, a
  **Web Browser** and probably a **Web Driver** for your browser).
