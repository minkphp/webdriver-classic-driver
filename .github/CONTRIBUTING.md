# Contributing to Mink WebDriver Classic Driver

At the moment, contributions can be in the form of:

1. [Reporting Bugs or Requesting Features](https://github.com/minkphp/webdriver-classic-driver/issues)
2. [Contributing Code (as Pull Requests)](https://github.com/minkphp/webdriver-classic-driver/pulls)

## Licensing

The changes you contribute (as Pull Requests) will be under the
same [MIT License](https://github.com/minkphp/webdriver-classic-driver/blob/main/LICENSE) used by this project.
Needless to say, it is your responsibility that your contribution is either your original work or that it has been
allowed by the original author.

## Code Quality

We use automated tools to have a general and consistent level of code quality. Simply run the following before each
commit  (or at least before sending a Pull Request):

```shell
composer run lint
```

## Testing

1. Firstly, you will need to run Selenium and a Web Browser (and/or perhaps a driver in between):
    1. **With Docker** - A `docker-compose.yml` file with sensible defaults is already provided , so you can just run:
         ```shell
         docker-compose up
         ```
    2. **With Java (Native)** - This would take more work, but performs better. Get started by running:
        ```shell
        curl -L https://github.com/SeleniumHQ/selenium/releases/download/selenium-4.18.0/selenium-server-4.18.1.jar > selenium-server-4.18.1.jar
        java -jar selenium-server-4.18.1.jar standalone
        ```
2. Finally, you can simply run the tests with:
    ```shell
    composer run test
    ```
