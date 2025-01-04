<?php

/**
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mink\WebdriverClassicDriver;

use Behat\Mink\Driver\CoreDriver;
use Behat\Mink\Exception\DriverException;
use Facebook\WebDriver\Exception\NoSuchCookieException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\ScriptTimeoutException;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Exception\UnsupportedOperationException;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverPlatform;
use Facebook\WebDriver\WebDriverRadios;
use Facebook\WebDriver\WebDriverSelect;
use JetBrains\PhpStorm\Language;

/**
 * @phpstan-type TWebDriverInstantiator callable(string $driverHost, DesiredCapabilities $capabilities): RemoteWebDriver
 */
class WebdriverClassicDriver extends CoreDriver
{
    public const DEFAULT_BROWSER = WebDriverBrowserType::CHROME;

    public const DEFAULT_CAPABILITIES = [
        'default' => [
            'platform' => 'ANY',
            'name' => 'Behat Test',
            'deviceOrientation' => 'landscape',
            'deviceType' => 'desktop',
        ],

        WebDriverBrowserType::CHROME => [
            'goog:chromeOptions' => [
                // disable "Chrome is being controlled.." notification bar
                'excludeSwitches' => ['enable-automation'],
            ],
        ],

        WebDriverBrowserType::MICROSOFT_EDGE => [
            'ms:edgeOptions' => [
                // disable "Microsoft Edge is being controlled.." notification bar
                'excludeSwitches' => ['enable-automation'],
                // disable menu shown when text is selected (which interferes with double-clicking)
                'prefs' => [
                    'edge_quick_search.show_mini_menu' => false,
                ],
            ],
        ],
    ];

    private const BROWSER_NAME_ALIAS_MAP = [
        'edge' => WebDriverBrowserType::MICROSOFT_EDGE,
        'chrome' => WebDriverBrowserType::CHROME,
        'chromium' => WebDriverBrowserType::CHROME,
        'firefox' => WebDriverBrowserType::FIREFOX,
    ];

    private const W3C_WINDOW_HANDLE_PREFIX = 'w3cwh:';

    private ?RemoteWebDriver $webDriver = null;

    private string $browserName;

    private DesiredCapabilities $desiredCapabilities;

    private array $timeouts = [];

    private string $webDriverHost;

    /**
     * @var TWebDriverInstantiator
     */
    private $webDriverInstantiator;

    private ?string $initialWindowHandle = null;

    /**
     * @param string $browserName One of 'edge', 'firefox', 'chrome' or any one of {@see WebDriverBrowserType} constants.
     * @param TWebDriverInstantiator|null $webDriverInstantiator
     */
    public function __construct(
        string $browserName = self::DEFAULT_BROWSER,
        array $desiredCapabilities = [],
        string $webDriverHost = 'http://localhost:4444/wd/hub',
        ?callable $webDriverInstantiator = null
    ) {
        $this->browserName = $browserName;
        $this->desiredCapabilities = $this->initCapabilities($desiredCapabilities);
        $this->webDriverHost = $webDriverHost;
        $this->webDriverInstantiator = $webDriverInstantiator ?? [self::class, 'instantiateWebDriver'];
    }

    // <editor-fold desc="Implementation">

    public function start(): void
    {
        try {
            $this->createWebDriver();
            $this->applyTimeouts();
            $this->initialWindowHandle = $this->getWebDriver()->getWindowHandle();
        } catch (\Throwable $e) {
            throw new DriverException("Could not start driver: {$e->getMessage()}", 0, $e);
        }
    }

    public function isStarted(): bool
    {
        return $this->webDriver !== null;
    }

    public function stop(): void
    {
        if (!$this->isStarted()) {
            throw new DriverException('Driver has not been started');
        }

        try {
            $this->getWebDriver()->quit();
        } catch (\Throwable $e) {
            throw new DriverException("Could not close connection: {$e->getMessage()}", 0, $e);
        } finally {
            $this->webDriver = null;
        }
    }

    /**
     * {@inheritdoc}
     * @throws DriverException
     */
    public function reset(): void
    {
        $webDriver = $this->getWebDriver();

        // Close all windows except the initial one.
        foreach ($webDriver->getWindowHandles() as $windowHandle) {
            if ($windowHandle === $this->initialWindowHandle) {
                continue;
            }

            $webDriver->switchTo()->window($windowHandle);
            $webDriver->close();
        }

        $this->switchToWindow();
        $webDriver->manage()->deleteAllCookies();
    }

    public function visit(string $url): void
    {
        try {
            $this->getWebDriver()->navigate()->to($url);
        } catch (TimeoutException|ScriptTimeoutException $e) {
            throw new DriverException('Page failed to load: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getCurrentUrl(): string
    {
        return $this->getWebDriver()->getCurrentURL();
    }

    public function reload(): void
    {
        $this->getWebDriver()->navigate()->refresh();
    }

    public function forward(): void
    {
        $this->getWebDriver()->navigate()->forward();
    }

    public function back(): void
    {
        $this->getWebDriver()->navigate()->back();
    }

    public function switchToWindow(?string $name = null): void
    {
        $handle = $name === null
            ? $this->initialWindowHandle
            : $this->getWindowHandleFromName($name);

        $this->getWebDriver()->switchTo()->window((string)$handle);
    }

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

    public function getContent(): string
    {
        return $this->getWebDriver()->getPageSource();
    }

    public function getScreenshot(): string
    {
        return $this->getWebDriver()->takeScreenshot();
    }

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

    public function getWindowName(): string
    {
        $name = (string)$this->evaluateScript('window.name');

        if ($name === '') {
            $name = self::W3C_WINDOW_HANDLE_PREFIX . $this->getWebDriver()->getWindowHandle();
        }

        return $name;
    }

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

    public function getTagName(
        #[Language('XPath')]
        string $xpath
    ): string {
        return $this->findElement($xpath)->getTagName();
    }

    public function getText(
        #[Language('XPath')]
        string $xpath
    ): string {
        return trim(str_replace(
            ["\r\n", "\r", "\n", "\xc2\xa0"],
            ' ',
            $this->getElementDomProperty($this->findElement($xpath), 'innerText')
        ));
    }

    public function getHtml(
        #[Language('XPath')]
        string $xpath
    ): string {
        return $this->getElementDomProperty($this->findElement($xpath), 'innerHTML');
    }

    public function getOuterHtml(
        #[Language('XPath')]
        string $xpath
    ): string {
        return $this->getElementDomProperty($this->findElement($xpath), 'outerHTML');
    }

    public function getAttribute(
        #[Language('XPath')]
        string $xpath,
        string $name
    ): ?string {
        // W3C spec deviates from expected behavior, (e.g. returns empty string instead of null for missing property),
        // so we cannot use webdriver api for this. See also: https://w3c.github.io/webdriver/#dfn-get-element-attribute
        $escapedName = $this->jsonEncode($name, 'get attribute', 'attribute name');
        $script = "return arguments[0].getAttribute($escapedName)";
        return $this->executeJsOnXpath($xpath, $script);
    }

    public function getValue(
        #[Language('XPath')]
        string $xpath
    ) {
        $element = $this->findElement($xpath);
        $widgetType = strtolower($element->getTagName() ?? '');
        if ($widgetType === 'input') {
            $widgetType = strtolower((string)$element->getAttribute('type'));
        }

        try {
            switch ($widgetType) {
                case 'radio':
                    $radioElement = new WebDriverRadios($element);
                    try {
                        return $radioElement->getFirstSelectedOption()->getAttribute('value');
                    } catch (NoSuchElementException $e) {
                        return null;
                    }

                case 'checkbox':
                    // WebDriverCheckboxes is not suitable since it _always_ behaves as a group
                    return $element->isSelected() ? $element->getAttribute('value') : null;

                case 'select':
                    $selectElement = new WebDriverSelect($element);
                    $selectedOptions = array_map(
                        static fn(WebDriverElement $option) => $option->getAttribute('value'),
                        $selectElement->getAllSelectedOptions()
                    );
                    return $selectElement->isMultiple() ? $selectedOptions : ($selectedOptions[0] ?? '');

                default:
                    return $this->getElementDomProperty($element, 'value');
            }
        } catch (\Throwable $e) {
            throw new DriverException("Cannot retrieve $widgetType value: {$e->getMessage()}", 0, $e);
        }
    }

    public function setValue(
        #[Language('XPath')]
        string $xpath,
        $value
    ): void {
        $element = $this->findElement($xpath);
        $widgetType = strtolower($element->getTagName() ?? '');
        if ($widgetType === 'input') {
            $widgetType = strtolower((string)$element->getAttribute('type'));
        }

        try {
            switch ($widgetType) {
                case 'select':
                    if (is_array($value)) {
                        $this->deselectAllOptions($element);
                        foreach ($value as $option) {
                            $this->selectOptionOnElement($element, $option, true);
                        }
                        return;
                    }
                    if (!is_string($value)) {
                        throw new DriverException("Value for $widgetType must be a string");
                    }
                    $this->selectOptionOnElement($element, $value);
                    return;

                case 'submit':
                case 'image':
                case 'button':
                case 'reset':
                    $message = 'Cannot set value on element with XPath "%s" as it is not a select, textarea or textbox';
                    throw new DriverException(sprintf($message, $xpath));

                case 'color':
                    if (!is_string($value)) {
                        throw new DriverException("Value for $widgetType must be a string");
                    }
                    // one cannot simply type into a color field, nor clear it
                    $this->setElementDomProperty($element, 'value', $value);
                    break;

                case 'date':
                case 'time':
                    if (!is_string($value)) {
                        throw new DriverException("Value for $widgetType must be a string");
                    }
                    try {
                        $element->clear();
                        $element->sendKeys($value);
                    } catch (WebDriverException $ex) {
                        // fix for Selenium 2 compatibility, since it's not able to clear or set these specific fields
                        $this->setElementDomProperty($element, 'value', $value);
                    }
                    break;

                case 'checkbox':
                    if (!is_bool($value)) {
                        throw new DriverException("Value for $widgetType must be a boolean");
                    }
                    if ($element->isSelected() xor $value) {
                        $this->clickOnElement($element);
                    }
                    return;

                case 'radio':
                    if (!is_string($value)) {
                        throw new DriverException("Value for $widgetType must be a string");
                    }
                    $this->selectRadioValue($element, $value);
                    return;

                case 'file':
                    if (!is_string($value)) {
                        throw new DriverException("Value for $widgetType must be a string");
                    }
                    $element->sendKeys($value);
                    break;

                case 'text':
                case 'password':
                case 'textarea':
                default:
                    if (!is_string($value)) {
                        throw new DriverException("Value for $widgetType must be a string");
                    }
                    $element->clear();
                    $element->sendKeys($value);
                    break;
            }
        } catch (\Throwable $e) {
            throw new DriverException("Cannot set $widgetType value: {$e->getMessage()}", 0, $e);
        }

        $this->trigger($xpath, 'blur');
    }

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

    public function isChecked(
        #[Language('XPath')]
        string $xpath
    ): bool {
        return $this->findElement($xpath)->isSelected();
    }

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

    public function isSelected(
        #[Language('XPath')]
        string $xpath
    ): bool {
        return $this->findElement($xpath)->isSelected();
    }

    public function click(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->clickOnElement($this->findElement($xpath));
    }

    public function doubleClick(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->doubleClickOnElement($this->findElement($xpath));
    }

    public function rightClick(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->rightClickOnElement($this->findElement($xpath));
    }

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

    public function isVisible(
        #[Language('XPath')]
        string $xpath
    ): bool {
        return $this->findElement($xpath)->isDisplayed();
    }

    public function mouseOver(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->mouseOverElement($this->findElement($xpath));
    }

    public function focus(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->trigger($xpath, 'focus');
    }

    public function blur(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->trigger($xpath, 'blur');
    }

    public function keyPress(
        #[Language('XPath')]
        string $xpath,
        $char,
        ?string $modifier = null
    ): void {
        $options = $this->charToSynOptions($char, $modifier);
        $this->trigger($xpath, 'keypress', $options);
    }

    public function keyDown(
        #[Language('XPath')]
        string $xpath,
        $char,
        ?string $modifier = null
    ): void {
        $options = $this->charToSynOptions($char, $modifier);
        $this->trigger($xpath, 'keydown', $options);
    }

    public function keyUp(
        #[Language('XPath')]
        string $xpath,
        $char,
        ?string $modifier = null
    ): void {
        $options = $this->charToSynOptions($char, $modifier);
        $this->trigger($xpath, 'keyup', $options);
    }

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

    public function submitForm(
        #[Language('XPath')]
        string $xpath
    ): void {
        $this->findElement($xpath)->submit();
    }

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

    // </editor-fold>

    // <editor-fold desc="Extension Points">

    /**
     * @throws DriverException
     */
    protected function createWebDriver(): void
    {
        if ($this->webDriver) {
            throw new DriverException('Base driver has already been created');
        }

        $this->webDriver = ($this->webDriverInstantiator)($this->webDriverHost, $this->desiredCapabilities);
    }

    /**
     * @throws DriverException
     */
    protected function getWebDriver(): RemoteWebDriver
    {
        if ($this->webDriver) {
            return $this->webDriver;
        }

        throw new DriverException('Base driver has not been created');
    }

    // </editor-fold>

    // <editor-fold desc="Private Utilities">

    private static function instantiateWebDriver(string $driverHost, DesiredCapabilities $capabilities): RemoteWebDriver
    {
        return RemoteWebDriver::create($driverHost, $capabilities);
    }

    private function getNormalisedBrowserName(): string
    {
        return self::BROWSER_NAME_ALIAS_MAP[$this->browserName] ?? $this->browserName;
    }

    /**
     * Detect and assign appropriate browser capabilities
     *
     * @see https://github.com/SeleniumHQ/selenium/wiki/DesiredCapabilities
     */
    private function initCapabilities(array $desiredCapabilities): DesiredCapabilities
    {
        $capabilities = $this->createBrowserSpecificCapabilities();

        foreach (
            array_merge(
                self::DEFAULT_CAPABILITIES['default'],
                self::DEFAULT_CAPABILITIES[$this->getNormalisedBrowserName()] ?? [],
                $desiredCapabilities,
            ) as $capabilityKey => $capabilityValue
        ) {
            $capabilities->setCapability($capabilityKey, $capabilityValue);
        }

        return $capabilities;
    }

    private function createBrowserSpecificCapabilities(): DesiredCapabilities
    {
        switch ($this->getNormalisedBrowserName()) {
            case WebDriverBrowserType::FIREFOX:
                return DesiredCapabilities::firefox();

            case WebDriverBrowserType::CHROME:
            case WebDriverBrowserType::GOOGLECHROME:
                return DesiredCapabilities::chrome();

            case WebDriverBrowserType::SAFARI:
                return DesiredCapabilities::safari();

            case WebDriverBrowserType::OPERA:
                return DesiredCapabilities::opera();

            case WebDriverBrowserType::MICROSOFT_EDGE:
                return DesiredCapabilities::microsoftEdge()
                    ->setCapability(WebDriverCapabilityType::PLATFORM, WebDriverPlatform::ANY);

            case WebDriverBrowserType::IE:
            case WebDriverBrowserType::IEXPLORE:
                return DesiredCapabilities::internetExplorer();

            case WebDriverBrowserType::ANDROID:
                return DesiredCapabilities::android();

            case WebDriverBrowserType::HTMLUNIT:
                return DesiredCapabilities::htmlUnit();

            case WebDriverBrowserType::IPHONE:
                return DesiredCapabilities::iphone();

            case WebDriverBrowserType::IPAD:
                return DesiredCapabilities::ipad();

            case WebDriverBrowserType::FIREFOX_PROXY:
            case WebDriverBrowserType::FIREFOX_CHROME:
            case WebDriverBrowserType::SAFARI_PROXY:
            case WebDriverBrowserType::IEXPLORE_PROXY:
            case WebDriverBrowserType::KONQUEROR:
            case WebDriverBrowserType::MOCK:
            case WebDriverBrowserType::IE_HTA:
            default:
                return new DesiredCapabilities();
        }
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

                    case 'page load':
                    case 'pageLoad':
                        @trigger_error(
                            "Using \"$type\" timeout type is deprecated, please use \"page\" instead",
                            E_USER_DEPRECATED
                        );
                    // no break

                    case 'page':
                        $timeouts->pageLoadTimeout($param / 1000);
                        break;

                    default:
                        throw new DriverException("Invalid timeout type: $type");
                }
            }
        } catch (\Throwable $e) {
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
        if ($name === null) {
            $callback();

            return;
        }

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
        ?RemoteWebElement $parent = null
    ): RemoteWebElement {
        try {
            $finder = WebDriverBy::xpath($xpath);
            return $parent
                ? $parent->findElement($finder)
                : $this->getWebDriver()->findElement($finder);
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\JsonException $e) {
            throw new DriverException("Cannot $action, $field not serializable: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param mixed $value
     * @throws DriverException
     */
    private function setElementDomProperty(RemoteWebElement $element, string $property, $value): void
    {
        $this->executeJsOnElement(
            $element,
            "arguments[0]['$property'] = {$this->jsonEncode($value, "set $property", $property)}"
        );
    }

    /**
     * @return mixed
     * @throws DriverException
     */
    private function getElementDomProperty(RemoteWebElement $element, string $property)
    {
        try {
            return $this->getWebDriver()->isW3cCompliant()
                ? $element->getDomProperty($property)
                : $this->executeJsOnElement($element, "return arguments[0]['$property']");
        } catch (UnsupportedOperationException $e) {
            $message = sprintf(
                'Could not get value of property "%s": %s',
                $property,
                $e->getMessage()
            );
            throw new DriverException($message, 0, $e);
        }
    }

    // </editor-fold>
}
