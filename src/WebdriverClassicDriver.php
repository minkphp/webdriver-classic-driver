<?php

/**
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @noinspection PhpLanguageLevelInspection
 */

namespace Mink\WebdriverClassDriver;

use Behat\Mink\Driver\CoreDriver;
use Behat\Mink\Exception\DriverException;
use Facebook\WebDriver\Exception\NoSuchCookieException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverRadios;
use Facebook\WebDriver\WebDriverSelect;
use JetBrains\PhpStorm\Language;
use JsonException;
use Throwable;

class WebdriverClassicDriver extends CoreDriver
{
    public const DEFAULT_BROWSER = 'chrome';

    public const DEFAULT_CAPABILITIES = [
        'default' => [
            'platform' => 'ANY',
            'name' => 'Behat Test',
            'deviceOrientation' => 'landscape',
            'deviceType' => 'desktop',
        ],
        'chrome' => [
            'goog:chromeOptions' => [
                'excludeSwitches' => ['enable-automation'],
            ],
        ],
        'firefox' => [
        ],
    ];

    private const W3C_WINDOW_HANDLE_PREFIX = 'w3cwh:';

    private ?RemoteWebDriver $webDriver = null;

    private string $browserName;

    private DesiredCapabilities $desiredCapabilities;

    private array $timeouts = [];

    private string $webDriverHost;

    private ?string $initialWindowName = null;

    /**
     * @throws DriverException
     */
    public function __construct(
        string $browserName = self::DEFAULT_BROWSER,
        array $desiredCapabilities = [],
        string $webDriverHost = 'http://localhost:4444/wd/hub'
    ) {
        $this->browserName = $browserName;
        $this->setDesiredCapabilities($this->initCapabilities($desiredCapabilities));
        $this->webDriverHost = $webDriverHost;
    }

    // <editor-fold desc="Implementation">

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        if ($this->isStarted()) {
            throw new DriverException('Driver has already been started');
        }

        try {
            $this->webDriver = RemoteWebDriver::create($this->webDriverHost, $this->desiredCapabilities);
            $this->applyTimeouts();
            $this->initialWindowName = $this->getWindowName();
        } catch (Throwable $e) {
            throw new DriverException("Could not start driver: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted(): bool
    {
        return $this->webDriver !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        if (!$this->webDriver) {
            throw new DriverException('Driver has not been started');
        }

        try {
            $this->getWebDriver()->quit();
            $this->webDriver = null;
        } catch (Throwable $e) {
            throw new DriverException('Could not close connection', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     * @throws DriverException
     */
    public function reset(): void
    {
        // switch to default window..
        $this->switchToWindow();
        // ..and close all other windows
        foreach ($this->getWindowNames() as $name) {
            if ($name !== $this->initialWindowName) {
                $this->withWindow($name, fn() => $this->getWebDriver()->close());
            }
        }

        $this->getWebDriver()->manage()->deleteAllCookies();
    }

    /**
     * {@inheritdoc}
     */
    public function visit(string $url): void
    {
        $this->getWebDriver()->navigate()->to($url);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentUrl(): string
    {
        return $this->getWebDriver()->getCurrentURL();
    }

    /**
     * {@inheritdoc}
     */
    public function reload(): void
    {
        $this->getWebDriver()->navigate()->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function forward(): void
    {
        $this->getWebDriver()->navigate()->forward();
    }

    /**
     * {@inheritdoc}
     */
    public function back(): void
    {
        $this->getWebDriver()->navigate()->back();
    }

    /**
     * {@inheritdoc}
     */
    public function switchToWindow(?string $name = null): void
    {
        if ($name === null) {
            $name = $this->initialWindowName;
        }

        if (is_string($name)) {
            $name = $this->getWindowHandleFromName($name);
        }

        $this->getWebDriver()->switchTo()->window((string)$name);
    }

    /**
     * {@inheritdoc}
     */
    public function switchToIFrame(?string $name = null): void
    {
        $frameQuery = $name;
        if ($name && $this->getWebDriver()->isW3cCompliant()) {
            try {
                $frameQuery = $this->getWebDriver()->findElement(WebDriverBy::id($name));
            } catch (NoSuchElementException $e) {
                $frameQuery = $this->getWebDriver()->findElement(WebDriverBy::name($name));
            }
        }

        $this->getWebDriver()->switchTo()->frame($frameQuery);
    }

    /**
     * {@inheritdoc}
     */
    public function setCookie(string $name, ?string $value = null): void
    {
        if (null === $value) {
            $this->getWebDriver()->manage()->deleteCookieNamed($name);

            return;
        }

        $cookieArray = [
            'name' => $name,
            'value' => rawurlencode($value),
            'secure' => false,
        ];

        $this->getWebDriver()->manage()->addCookie($cookieArray);
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie(string $name): ?string
    {
        try {
            $result = $this->getWebDriver()->manage()->getCookieNamed($name);
        } catch (NoSuchCookieException $e) {
            $result = null;
        }
        if ($result === null) {
            return null;
        }

        return rawurldecode($result->getValue());
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): string
    {
        return $this->getWebDriver()->getPageSource();
    }

    /**
     * {@inheritdoc}
     */
    public function getScreenshot(): string
    {
        return $this->getWebDriver()->takeScreenshot();
    }

    /**
     * {@inheritdoc}
     */
    public function getWindowNames(): array
    {
        $origWindow = $this->getWebDriver()->getWindowHandle();

        try {
            $result = [];
            foreach ($this->getWebDriver()->getWindowHandles() as $tempWindow) {
                $this->getWebDriver()->switchTo()->window($tempWindow);
                $result[] = $this->getWindowName();
            }
            return $result;
        } finally {
            $this->getWebDriver()->switchTo()->window($origWindow);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getWindowName(): string
    {
        $name = (string)$this->evaluateScript('window.name');

        if ($name === '') {
            $name = self::W3C_WINDOW_HANDLE_PREFIX . $this->getWebDriver()->getWindowHandle();
        }

        return $name;
    }

    /**
     * {@inheritdoc}
     */
    protected function findElementXpaths(
        #[Language('XPath')]
        string $xpath
    ): array {
        $nodes = $this->getWebDriver()->findElements(WebDriverBy::xpath($xpath));

        $elements = [];
        foreach ($nodes as $i => $node) {
            $elements[] = sprintf('(%s)[%d]', $xpath, $i + 1);
        }

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function getTagName(
        #[Language('XPath')]
        string $xpath
    ): string {
        return $this->findElement($xpath)->getTagName();
    }

    /**
     * {@inheritdoc}
     */
    public function getText(
        #[Language('XPath')]
        string $xpath
    ): string {
        return str_replace(["\r", "\n"], ' ', $this->findElement($xpath)->getText());
    }

    /**
     * {@inheritdoc}
     */
    public function getHtml(
        #[Language('XPath')]
        string $xpath
    ): string {
        return $this->executeJsOnXpath($xpath, 'return arguments[0].innerHTML;');
    }

    /**
     * {@inheritdoc}
     */
    public function getOuterHtml(
        #[Language('XPath')]
        string $xpath
    ): string {
        return $this->executeJsOnXpath($xpath, 'return arguments[0].outerHTML;');
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute(
        #[Language('XPath')]
        string $xpath,
        string $name
    ): ?string {
        $escapedName = $this->jsonEncode($name, 'get attribute', 'attribute name');
        $script = "return arguments[0].getAttribute($escapedName)";

        return $this->executeJsOnXpath($xpath, $script);
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(
        #[Language('XPath')]
        string $xpath
    ) {
        $element = $this->findElement($xpath);
        $elementName = strtolower($element->getTagName() ?? '');
        $elementType = strtolower((string)$element->getAttribute('type'));
        $widgetType = $elementName === 'input' ? $elementType : $elementName;

        try {
            switch (true) {
                case $widgetType === 'radio':
                    $radioElement = new WebDriverRadios($element);
                    try {
                        return $radioElement->getFirstSelectedOption()->getAttribute('value');
                    } catch (NoSuchElementException $e) {
                        return null;
                    }

                case $widgetType === 'checkbox':
                    // WebDriverCheckboxes is not suitable since it _always_ behaves as a group
                    return $element->isSelected() ? $element->getAttribute('value') : null;

                case $widgetType === 'select':
                    $selectElement = new WebDriverSelect($element);
                    $selectedOptions = array_map(
                        static fn(WebDriverElement $option) => $option->getAttribute('value'),
                        $selectElement->getAllSelectedOptions()
                    );
                    return $selectElement->isMultiple() ? $selectedOptions : ($selectedOptions[0] ?? '');

                default:
                    return $this->getWebDriver()->isW3cCompliant()
                        ? $element->getDomProperty('value')
                        : $this->executeJsOnElement($element, 'return arguments[0].value');
            }
        } catch (Throwable $e) {
            throw new DriverException("Cannot retrieve $widgetType value: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(
        #[Language('XPath')]
        string $xpath,
        $value
    ): void {
        $element = $this->findElement($xpath);
        $elementName = strtolower($element->getTagName() ?? '');

        switch ($elementName) {
            case 'textarea':
                if (!is_string($value)) {
                    throw new DriverException('Textarea value must be a string');
                }
                $element->clear();
                $element->sendKeys($value);
                break;

            case 'select':
                if (is_array($value)) {
                    $this->deselectAllOptions($element);
                    foreach ($value as $option) {
                        $this->selectOptionOnElement($element, $option, true);
                    }
                    return;
                }
                $this->selectOptionOnElement($element, (string)$value);
                return;

            case 'input':
                $elementType = strtolower((string)$element->getAttribute('type'));
                switch ($elementType) {
                    case 'submit':
                    case 'image':
                    case 'button':
                    case 'reset':
                        $message = 'Cannot set value on element with XPath "%s" as it is not a select, textarea or textbox';
                        throw new DriverException(sprintf($message, $xpath));

                    case 'color':
                        if (!is_string($value)) {
                            throw new DriverException('Color value must be a string');
                        }
                        // one cannot simply type into a color field, nor clear it
                        $this->executeJsOnElement(
                            $element,
                            'arguments[0].value = ' . $this->jsonEncode($value, 'set value', 'value')
                        );
                        break;

                    case 'date':
                    case 'time':
                        try {
                            $element->clear();
                            $element->sendKeys($value);
                        } catch (WebDriverException $ex) {
                            // fix for Selenium 2 compatibility, since it's not able to clear these specific fields
                            $this->executeJsOnElement(
                                $element,
                                'arguments[0].value = ' . $this->jsonEncode($value, 'set value', 'value')
                            );
                        }
                        break;

                    case 'checkbox':
                        if ($element->isSelected() xor $value) {
                            $this->clickOnElement($element);
                        }
                        return;

                    case 'radio':
                        if (!is_string($value)) {
                            throw new DriverException('Value must be a string');
                        }
                        $this->selectRadioValue($element, $value);
                        return;

                    case 'file':
                        if (!is_string($value)) {
                            throw new DriverException('Value must be a string');
                        }
                        $element->sendKeys($value);
                        break;

                    default:
                        if (!is_string($value)) {
                            throw new DriverException('Value must be a string');
                        }
                        $element->clear();
                        $element->sendKeys($value);
                        break;
                }
        }

        $this->trigger($xpath, 'blur');
    }

    /**
     * {@inheritdoc}
     */
    public function check(
        #[Language('XPath')]
        string $xpath
    ): void {
        $element = $this->findElement($xpath);
        $this->ensureInputType($element, $xpath, 'checkbox', 'check');

        if ($element->isSelected()) {
            return;
        }

        $this->clickOnElement($element);
    }

    /**
     * {@inheritdoc}
     */
    public function uncheck(
        #[Language('XPath')]
        string $xpath
    ): void {
        $element = $this->findElement($xpath);
        $this->ensureInputType($element, $xpath, 'checkbox', 'uncheck');

        if (!$element->isSelected()) {
            return;
        }

        $this->clickOnElement($element);
    }

    /**
     * {@inheritdoc}
     */
    public function isChecked(
        #[Language('XPath')]
        string $xpath
    ): bool {
        return $this->findElement($xpath)->isSelected();
    }

    /**
     * {@inheritdoc}
     */
    public function selectOption(
        #[Language('XPath')]
        string $xpath,
        string $value,
        bool $multiple = false
    ): void {
        $element = $this->findElement($xpath);
        $tagName = strtolower($element->getTagName() ?? '');

        if ($tagName === 'input' && strtolower((string)$element->getAttribute('type')) === 'radio') {
            $this->selectRadioValue($element, $value);
            return;
        }

        if ($tagName === 'select') {
            $this->selectOptionOnElement($element, $value, $multiple);
            return;
        }

        $message = 'Impossible to select an option on the element with XPath "%s" as it is not a select or radio input';
        throw new DriverException(sprintf($message, $xpath));
    }

    /**
     * {@inheritdoc}
     */
    public function isSelected(
        #[Language('XPath')]
        string $xpath
    ): bool {
        return $this->findElement($xpath)->isSelected();
    }

    /**
     * {@inheritdoc}
     */
    public function click(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->clickOnElement($this->findElement($xpath));
    }

    /**
     * {@inheritdoc}
     */
    public function doubleClick(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->doubleClickOnElement($this->findElement($xpath));
    }

    /**
     * {@inheritdoc}
     */
    public function rightClick(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->rightClickOnElement($this->findElement($xpath));
    }

    /**
     * {@inheritdoc}
     */
    public function attachFile(
        #[Language('XPath')]
        string $xpath,
        #[Language('file-reference')]
        string $path
    ): void {
        $element = $this->findElement($xpath);
        $this->ensureInputType($element, $xpath, 'file', 'attach a file on');
        $this->setValue($xpath, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function isVisible(
        #[Language('XPath')]
        string $xpath
    ): bool {
        return $this->findElement($xpath)->isDisplayed();
    }

    /**
     * {@inheritdoc}
     */
    public function mouseOver(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->mouseOverElement($this->findElement($xpath));
    }

    /**
     * {@inheritdoc}
     */
    public function focus(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->trigger($xpath, 'focus');
    }

    /**
     * {@inheritdoc}
     */
    public function blur(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->trigger($xpath, 'blur');
    }

    /**
     * {@inheritdoc}
     */
    public function keyPress(
        #[Language('XPath')]
        string $xpath,
        $char,
        ?string $modifier = null
    ): void {
        $options = $this->charToSynOptions($char, $modifier);
        $this->trigger($xpath, 'keypress', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function keyDown(
        #[Language('XPath')]
        string $xpath,
        $char,
        ?string $modifier = null
    ): void {
        $options = $this->charToSynOptions($char, $modifier);
        $this->trigger($xpath, 'keydown', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function keyUp(
        #[Language('XPath')]
        string $xpath,
        $char,
        ?string $modifier = null
    ): void {
        $options = $this->charToSynOptions($char, $modifier);
        $this->trigger($xpath, 'keyup', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function dragTo(
        #[Language('XPath')]
        string $sourceXpath,
        #[Language('XPath')]
        string $destinationXpath
    ): void {
        $source = $this->findElement($sourceXpath);
        $destination = $this->findElement($destinationXpath);
        $this->getWebDriver()->action()->dragAndDrop($source, $destination)->perform();
    }

    /**
     * {@inheritdoc}
     */
    public function executeScript(
        #[Language('JavaScript')]
        string $script
    ): void {
        if (preg_match('/^function[\s(]/', $script)) {
            $script = '(' . rtrim($script, ';') . ')';
        }

        $this->getWebDriver()->executeScript($script);
    }

    /**
     * {@inheritdoc}
     * @return mixed
     */
    public function evaluateScript(
        #[Language('JavaScript')]
        string $script
    ) {
        if (strncmp(ltrim($script), 'return ', 7) !== 0) {
            $script = "return $script;";
        }

        return $this->getWebDriver()->executeScript($script);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(
        int $timeout,
        #[Language('JavaScript')]
        string $condition
    ): bool {
        $start = microtime(true);
        $end = $start + $timeout / 1000.0;

        do {
            $result = $this->evaluateScript($condition);
            usleep(100000);
        } while (microtime(true) < $end && !$result);

        return (bool)$result;
    }

    /**
     * {@inheritdoc}
     */
    public function resizeWindow(int $width, int $height, ?string $name = null): void
    {
        $this->withWindow(
            $name,
            fn() => $this
                ->getWebDriver()
                ->manage()
                ->window()
                ->setSize(new WebDriverDimension($width, $height))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->findElement($xpath)->submit();
    }

    /**
     * {@inheritdoc}
     */
    public function maximizeWindow(?string $name = null): void
    {
        $this->withWindow(
            $name,
            fn() => $this
                ->getWebDriver()
                ->manage()
                ->window()
                ->maximize()
        );
    }

    // </editor-fold>

    // <editor-fold desc="Extra Public API">

    /**
     * Returns the browser name.
     *
     * @api
     */
    public function getBrowserName(): string
    {
        return $this->browserName;
    }

    /**
     * Returns Session ID of WebDriver or `null`, when session not started yet.
     *
     * @return string|null
     * @throws DriverException
     * @api
     */
    public function getWebDriverSessionId(): ?string
    {
        return $this->isStarted()
            ? $this->getWebDriver()->getSessionID()
            : null;
    }

    /**
     * Sets the desired capabilities - called on construction.
     *
     * @see http://code.google.com/p/selenium/wiki/DesiredCapabilities
     *
     * @api
     * @param array|DesiredCapabilities $desiredCapabilities
     * @throws DriverException
     */
    public function setDesiredCapabilities($desiredCapabilities): self
    {
        if ($this->isStarted()) {
            throw new DriverException('Unable to set desiredCapabilities, the session has already started');
        }

        if (is_array($desiredCapabilities)) {
            $desiredCapabilities = new DesiredCapabilities($desiredCapabilities);
        }

        $this->desiredCapabilities = $desiredCapabilities;

        return $this;
    }

    /**
     * Sets the timeouts to apply to the webdriver session
     *
     * @param array $timeouts The session timeout settings: Array of {script, implicit, page} => time in milliseconds
     * @throws DriverException
     * @api
     */
    public function setTimeouts(array $timeouts): void
    {
        $this->timeouts = $timeouts;

        if ($this->isStarted()) {
            $this->applyTimeouts();
        }
    }

    /**
     * Gets the final desired capabilities (as sent to Selenium).
     *
     * @see http://code.google.com/p/selenium/wiki/DesiredCapabilities
     *
     * @api
     */
    public function getDesiredCapabilities(): array
    {
        return $this->desiredCapabilities->toArray();
    }

    /**
     * Globally press a key i.e. not typing into an element.
     *
     * @throws DriverException
     * @api
     */
    public function globalKeyPress(string $char, ?string $modifier = null): void
    {
        $keyboard = $this->getWebDriver()->getKeyboard();
        if ($modifier) {
            $keyboard->pressKey($modifier);
        }
        $keyboard->pressKey($char);
        if ($modifier) {
            $keyboard->releaseKey($modifier);
        }
    }

    /**
     * Drag and drop an element by x,y pixels.
     *
     * @throws DriverException
     * @api
     */
    public function dragBy(
        #[Language('XPath')]
        string $sourceXpath,
        int $xOffset,
        int $yOffset
    ): void {
        $source = $this->findElement($sourceXpath);
        $this->getWebDriver()->action()->dragAndDropBy($source, $xOffset, $yOffset)->perform();
    }

    // </editor-fold>

    // <editor-fold desc="Private Utilities">

    /**
     * @throws DriverException
     */
    private function getWebDriver(): RemoteWebDriver
    {
        if ($this->webDriver) {
            return $this->webDriver;
        }

        throw new DriverException('Driver has not been started');
    }

    /**
     * Detect and assign appropriate browser capabilities
     *
     * @see https://github.com/SeleniumHQ/selenium/wiki/DesiredCapabilities
     */
    private function initCapabilities(array $desiredCapabilities): DesiredCapabilities
    {
        // Build base capabilities
        $browserName = $this->browserName;
        if ($browserName && method_exists(DesiredCapabilities::class, $browserName)) {
            /** @var DesiredCapabilities $caps */
            $caps = DesiredCapabilities::$browserName();
        } else {
            $caps = new DesiredCapabilities();
        }

        // Set defaults
        $defaults = array_merge(
            self::DEFAULT_CAPABILITIES['default'],
            self::DEFAULT_CAPABILITIES[$browserName] ?? []
        );
        foreach ($defaults as $key => $value) {
            if (is_null($caps->getCapability($key))) {
                $caps->setCapability($key, $value);
            }
        }

        // Merge in other requested types
        foreach ($desiredCapabilities as $key => $value) {
            $caps->setCapability($key, $value);
        }

        return $caps;
    }

    /**
     * @throws DriverException
     */
    private function withSyn(): self
    {
        $hasSyn = $this->evaluateScript(
            'return window.syn !== undefined && window.syn.trigger !== undefined'
        );
        if ($hasSyn) {
            return $this;
        }

        $synJs = file_get_contents(__DIR__ . '/../resources/syn.js');
        if (!$synJs) {
            throw new DriverException('Could not load syn.js resource');
        }

        $this->getWebDriver()->executeScript($synJs);
        return $this;
    }

    /**
     * @param int|string $char
     * @throws DriverException
     */
    private function charToSynOptions($char, ?string $modifier = null): string
    {
        if (is_int($char)) {
            $charCode = $char;
            $char = chr($charCode);
        } else {
            $charCode = ord($char);
        }

        $options = [
            'key' => $char,
            'which' => $charCode,
            'charCode' => $charCode,
            'keyCode' => $charCode,
        ];

        if ($modifier) {
            $options[$modifier . 'Key'] = true;
        }

        return $this->jsonEncode($options, 'build Syn payload', 'options');
    }

    /**
     * Executes JS on a given element - pass in a js script string and argument[0] will
     * be replaced with a reference to the result of the $xpath query
     *
     * @param string $xpath the xpath to search with
     * @param string $script the script to execute
     *
     * @return mixed
     * @throws DriverException
     * @example $this->executeJsOnXpath($xpath, 'return argument[0].childNodes.length');
     */
    private function executeJsOnXpath(
        #[Language('XPath')]
        string $xpath,
        #[Language('JavaScript')]
        string $script
    ) {
        return $this->executeJsOnElement($this->findElement($xpath), $script);
    }

    /**
     * Executes JS on a given element - pass in a js script string and argument[0] will contain a reference to the element
     *
     * @param RemoteWebElement $element the webdriver element
     * @param string $script the script to execute
     * @return mixed
     * @throws DriverException
     * @example $this->executeJsOnXpath($xpath, 'return argument[0].childNodes.length');
     */
    private function executeJsOnElement(
        RemoteWebElement $element,
        #[Language('JavaScript')]
        string $script
    ) {
        return $this->getWebDriver()->executeScript($script, [$element]);
    }

    /**
     * @throws DriverException
     */
    private function applyTimeouts(): void
    {
        try {
            $timeouts = $this->getWebDriver()->manage()->timeouts();
            foreach ($this->timeouts as $type => $param) {
                switch ($type) {
                    case 'script':
                        $timeouts->setScriptTimeout($param / 1000);
                        break;
                    case 'implicit':
                        $timeouts->implicitlyWait($param / 1000);
                        break;
                    case 'page':
                        $timeouts->pageLoadTimeout($param / 1000);
                        break;
                    default:
                        throw new DriverException("Invalid timeout type: $type");
                }
            }
        } catch (Throwable $e) {
            throw new DriverException("Error setting timeout: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @throws DriverException
     */
    private function getWindowHandleFromName(string $name): string
    {
        // if name is actually prefixed window handle, just remove the prefix
        if (strpos($name, self::W3C_WINDOW_HANDLE_PREFIX) === 0) {
            return substr($name, strlen(self::W3C_WINDOW_HANDLE_PREFIX));
        }

        // ..otherwise check if any existing window has the specified name

        $origWindowHandle = $this->getWebDriver()->getWindowHandle();

        try {
            foreach ($this->getWebDriver()->getWindowHandles() as $handle) {
                $this->getWebDriver()->switchTo()->window($handle);
                if ($this->evaluateScript('window.name') === $name) {
                    return $handle;
                }
            }

            throw new DriverException("Could not find handle of window named \"$name\"");
        } finally {
            $this->getWebDriver()->switchTo()->window($origWindowHandle);
        }
    }

    private function clickOnElement(RemoteWebElement $element): void
    {
        $element->getLocationOnScreenOnceScrolledIntoView();
        $element->click();
    }

    /**
     * @throws DriverException
     */
    private function doubleClickOnElement(RemoteWebElement $element): void
    {
        $element->getLocationOnScreenOnceScrolledIntoView();
        $this->getWebDriver()->getMouse()->doubleClick($element->getCoordinates());
    }

    /**
     * @throws DriverException
     */
    private function rightClickOnElement(RemoteWebElement $element): void
    {
        $element->getLocationOnScreenOnceScrolledIntoView();
        $this->getWebDriver()->getMouse()->contextClick($element->getCoordinates());
    }

    /**
     * @throws DriverException
     */
    private function mouseOverElement(RemoteWebElement $element): void
    {
        $element->getLocationOnScreenOnceScrolledIntoView();
        $this->getWebDriver()->getMouse()->mouseMove($element->getCoordinates());
    }

    /**
     * @throws DriverException
     */
    private function withWindow(?string $name, callable $callback): void
    {
        $origName = $this->getWindowName();

        try {
            if ($origName !== $name) {
                $this->switchToWindow($name);
            }

            $callback();
        } finally {
            if ($origName !== $name) {
                $this->switchToWindow($origName);
            }
        }
    }

    /**
     * @throws DriverException
     */
    private function findElement(
        #[Language('XPath')]
        string $xpath,
        RemoteWebElement $parent = null
    ): RemoteWebElement {
        try {
            $finder = WebDriverBy::xpath($xpath);
            return $parent
                ? $parent->findElement($finder)
                : $this->getWebDriver()->findElement($finder);
        } catch (Throwable $e) {
            throw new DriverException("Failed to find element: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @throws DriverException
     */
    private function selectRadioValue(RemoteWebElement $element, string $value): void
    {
        try {
            (new WebDriverRadios($element))->selectByValue($value);
        } catch (Throwable $e) {
            $message = sprintf(
                'Cannot select radio button of group "%s" with value "%s": %s',
                $element->getAttribute('name'),
                $value,
                $e->getMessage()
            );
            throw new DriverException($message, 0, $e);
        }
    }

    /**
     * @throws DriverException
     */
    private function selectOptionOnElement(RemoteWebElement $element, string $value, bool $multiple = false): void
    {
        try {
            $select = new WebDriverSelect($element);
            if (!$multiple && $select->isMultiple()) {
                $select->deselectAll();
            }
            try {
                $select->selectByValue($value);
            } catch (NoSuchElementException $e) {
                $select->selectByVisibleText($value);
            }
        } catch (Throwable $e) {
            $message = sprintf(
                'Cannot select option "%s" of "%s": %s',
                $value,
                $element->getAttribute('name'),
                $e->getMessage(),
            );
            throw new DriverException($message, 0, $e);
        }
    }

    /**
     * Deselects all options of a multiple select
     *
     * Note: this implementation does not trigger a change event after deselecting the elements.
     *
     * @throws DriverException
     */
    private function deselectAllOptions(RemoteWebElement $element): void
    {
        try {
            (new WebDriverSelect($element))->deselectAll();
        } catch (Throwable $e) {
            $message = sprintf(
                'Cannot deselect all options of "%s": %s',
                $element->getAttribute('name'),
                $e->getMessage()
            );
            throw new DriverException($message, 0, $e);
        }
    }

    /**
     * @throws DriverException
     */
    private function ensureInputType(
        RemoteWebElement $element,
        #[Language('XPath')]
        string $xpath,
        string $type,
        string $action
    ): void {
        if ($element->getTagName() !== 'input' || $element->getAttribute('type') !== $type) {
            throw new DriverException(
                "Impossible to $action the element with XPath \"$xpath\" as it is not a $type input"
            );
        }
    }

    /**
     * @throws DriverException
     */
    private function trigger(
        #[Language('XPath')]
        string $xpath,
        string $event,
        #[Language('JSON')]
        string $options = '{}'
    ): void {
        $this->withSyn()->executeJsOnXpath($xpath, "window.syn.trigger(arguments[0], '$event', $options)");
    }

    /**
     * @param mixed $value
     * @throws DriverException
     */
    private function jsonEncode($value, string $action, string $field): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DriverException("Cannot $action, $field not serializable: {$e->getMessage()}", 0, $e);
        }
    }

    // </editor-fold>
}
