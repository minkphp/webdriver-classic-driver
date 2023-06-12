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
use Behat\Mink\Selector\Xpath\Escaper;
use Facebook\WebDriver\Exception\NoSuchCookieException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
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

    private bool $started = false;

    private array $timeouts = [];

    private Escaper $xpathEscaper;

    private string $webDriverHost;

    private ?string $initialWindowName = null;

    /**
     * @throws DriverException
     */
    public function __construct(
        string  $browserName = null,
        array   $desiredCapabilities = null,
        string  $webDriverHost = null,
        Escaper $xpathEscaper = null
    ) {
        $this->browserName = $browserName ?? self::DEFAULT_BROWSER;
        $this->setDesiredCapabilities($this->initCapabilities($desiredCapabilities ?? []));
        $this->webDriverHost = $webDriverHost ?? 'http://localhost:4444/wd/hub';
        $this->xpathEscaper = $xpathEscaper ?? new Escaper();
    }

    // <editor-fold desc="Implementation">

    /**
     * {@inheritdoc}
     * @throws DriverException
     */
    public function start(): void
    {
        try {
            $this->webDriver = RemoteWebDriver::create($this->webDriverHost, $this->desiredCapabilities);
            $this->applyTimeouts();
            $this->initialWindowName = $this->getWindowName();
            $this->started = true;
        } catch (Throwable $e) {
            throw new DriverException("Could not open connection: {$e->getMessage()}", 0, $e);
        }
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
     * {@inheritdoc}
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * {@inheritdoc}
     * @throws DriverException
     */
    public function stop(): void
    {
        if (!$this->webDriver) {
            throw new DriverException('Could not connect to a Selenium / WebDriver server');
        }

        try {
            $this->started = false;
            $this->webDriver->quit();
        } catch (Throwable $e) {
            throw new DriverException('Could not close connection', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->webDriver->manage()->deleteAllCookies();
    }

    /**
     * {@inheritdoc}
     */
    public function visit($url): void
    {
        $this->webDriver->navigate()->to($url);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentUrl(): string
    {
        return $this->webDriver->getCurrentURL();
    }

    /**
     * {@inheritdoc}
     */
    public function reload(): void
    {
        $this->webDriver->navigate()->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function forward(): void
    {
        $this->webDriver->navigate()->forward();
    }

    /**
     * {@inheritdoc}
     */
    public function back(): void
    {
        $this->webDriver->navigate()->back();
    }

    /**
     * {@inheritdoc}
     * @throws DriverException
     */
    public function switchToWindow($name = null): void
    {
        if ($name === null) {
            $name = $this->initialWindowName;
        }

        if (is_string($name)) {
            $name = $this->getWindowHandleFromName($name);
        }

        $this->webDriver->switchTo()->window($name);
    }

    /**
     * {@inheritdoc}
     */
    public function switchToIFrame($name = null): void
    {
        $frameQuery = $name;
        if ($name && is_string($name) && $this->webDriver->isW3cCompliant()) {
            try {
                $frameQuery = $this->webDriver->findElement(WebDriverBy::id($name));
            } catch (NoSuchElementException $e) {
                $frameQuery = $this->webDriver->findElement(WebDriverBy::name($name));
            }
        }

        $this->webDriver->switchTo()->frame($frameQuery);
    }

    /**
     * {@inheritdoc}
     */
    public function setCookie($name, $value = null): void
    {
        if (null === $value) {
            $this->webDriver->manage()->deleteCookieNamed($name);

            return;
        }

        $cookieArray = [
            'name' => $name,
            'value' => rawurlencode($value),
            'secure' => false,
        ];

        $this->webDriver->manage()->addCookie($cookieArray);
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie($name): ?string
    {
        try {
            $result = $this->webDriver->manage()->getCookieNamed($name);
        } catch (NoSuchCookieException $e) {
            $result = null;
        }
        if ($result === null) {
            return null;
        }

        $result = $result->getValue();
        if ($result === null) {
            return null;
        }

        return rawurldecode($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): string
    {
        return $this->webDriver->getPageSource();
    }

    /**
     * {@inheritdoc}
     */
    public function getScreenshot(): string
    {
        return $this->webDriver->takeScreenshot();
    }

    /**
     * {@inheritdoc}
     */
    public function getWindowNames(): array
    {
        $origWindow = $this->webDriver->getWindowHandle();

        try {
            $result = [];
            foreach ($this->webDriver->getWindowHandles() as $tempWindow) {
                $this->webDriver->switchTo()->window($tempWindow);
                $result[] = $this->getWindowName();
            }
            return $result;
        } finally {
            $this->webDriver->switchTo()->window($origWindow);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getWindowName(): string
    {
        $name = (string)$this->evaluateScript('window.name');

        if ($name === '') {
            $name = self::W3C_WINDOW_HANDLE_PREFIX . $this->webDriver->getWindowHandle();
        }

        return $name;
    }

    /**
     * {@inheritdoc}
     */
    public function findElementXpaths(
        #[Language('XPath')]
        $xpath
    ): array {
        $nodes = $this->webDriver->findElements(WebDriverBy::xpath($xpath));

        $elements = [];
        foreach ($nodes as $i => $node) {
            $elements[] = sprintf('(%s)[%d]', $xpath, $i + 1);
        }

        return $elements;
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function getTagName(
        #[Language('XPath')]
        $xpath
    ): string {
        return $this->findElement($xpath)->getTagName();
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function getText(
        #[Language('XPath')]
        $xpath
    ): string {
        return str_replace(["\r", "\n"], ' ', $this->findElement($xpath)->getText());
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function getHtml(
        #[Language('XPath')]
        $xpath
    ): string {
        return $this->executeJsOnXpath($xpath, 'return arguments[0].innerHTML;');
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function getOuterHtml(
        #[Language('XPath')]
        $xpath
    ): string {
        return $this->executeJsOnXpath($xpath, 'return arguments[0].outerHTML;');
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     * @throws NoSuchElementException
     */
    public function getAttribute(
        #[Language('XPath')]
        $xpath,
        $name
    ): ?string {
        $escapedName = json_encode((string)$name, JSON_THROW_ON_ERROR);
        $script = "return arguments[0].getAttribute($escapedName)";

        return $this->executeJsOnXpath($xpath, $script);
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function getValue(
        #[Language('XPath')]
        $xpath
    ) {
        $element = $this->findElement($xpath);
        $elementName = strtolower($element->getTagName() ?? '');
        $elementType = strtolower($element->getAttribute('type') ?? '');

        // Getting the value of a checkbox returns its value if selected.
        if ('input' === $elementName && 'checkbox' === $elementType) {
            return $element->isSelected() ? $element->getAttribute('value') : null;
        }

        if ('input' === $elementName && 'radio' === $elementType) {
            $script = <<<JS
                var node = arguments[0],
                    value = null;

                var name = node.getAttribute('name');
                if (name) {
                    var fields = window.document.getElementsByName(name),
                        i, l = fields.length;
                    for (i = 0; i < l; i++) {
                        var field = fields.item(i);
                        if (field.form === node.form && field.checked) {
                            value = field.value;
                            break;
                        }
                    }
                }

                return value;
                JS;

            return $this->executeJsOnElement($element, $script);
        }

        // Using $element->attribute('value') on a select only returns the first selected option
        // even when it is a multiple select, so a custom retrieval is needed.
        if ('select' === $elementName && $element->getAttribute('multiple')) {
            $script = <<<JS
                var node = arguments[0],
                    value = [];

                for (var i = 0; i < node.options.length; i++) {
                    if (node.options[i].selected) {
                        value.push(node.options[i].value);
                    }
                }

                return value;
                JS;

            return $this->executeJsOnElement($element, $script);
        }

        // use textarea.value rather than textarea.getAttribute(value) for chrome 91+ support
        if ('textarea' === $elementName) {
            $script = <<<JS
                var node = arguments[0];
                return node.value;
                JS;
            return $this->executeJsOnElement($element, $script);
        }

        return $element->getAttribute('value');
    }

    /**
     * {@inheritdoc}
     * @throws DriverException
     * @throws NoSuchElementException
     * @throws JsonException
     */
    public function setValue(
        #[Language('XPath')]
        $xpath,
        $value
    ): void {
        $element = $this->findElement($xpath);
        $elementName = strtolower($element->getTagName() ?? '');

        switch ($elementName) {
            case 'select':
                if (is_array($value)) {
                    $this->deselectAllOptions($element);
                    foreach ($value as $option) {
                        $this->selectOptionOnElement($element, $option, true);
                    }
                    return;
                }
                $this->selectOptionOnElement($element, $value);
                return;

            case 'textarea':
                $element->clear();
                $element->sendKeys($value);
                break;

            case 'input':
                $elementType = strtolower($element->getAttribute('type') ?? '');
                switch ($elementType) {
                    case 'submit':
                    case 'image':
                    case 'button':
                    case 'reset':
                        $message = 'Cannot set value an element with XPath "%s" as it is not a select, textarea or textbox';
                        throw new DriverException(sprintf($message, $xpath));

                    case 'color':
                        // one cannot simply type into a color field, nor clear it
                        $this->executeJsOnElement(
                            $element,
                            'arguments[0].value = ' . json_encode($value, JSON_THROW_ON_ERROR)
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
                                'arguments[0].value = ' . json_encode($value, JSON_THROW_ON_ERROR)
                            );
                        }
                        break;

                    case 'checkbox':
                        if ($element->isSelected() xor $value) {
                            $this->clickOnElement($element);
                        }
                        return;

                    case 'radio':
                        $this->selectRadioValue($element, $value);
                        return;

                    case 'file':
                        // @todo - Check if this is correct way to upload files
                        $element->sendKeys($value);
                        // $element->postValue(['value' => [(string)$value]]);
                        return;

                    default:
                        $element->clear();
                        $element->sendKeys($value);
                }
        }

        $this->trigger($xpath, 'blur');
    }

    /**
     * {@inheritdoc}
     * @throws DriverException
     * @throws NoSuchElementException
     */
    public function check(
        #[Language('XPath')]
        $xpath
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
     * @throws DriverException
     * @throws NoSuchElementException
     */
    public function uncheck(
        #[Language('XPath')]
        $xpath
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
     * @throws NoSuchElementException
     */
    public function isChecked(
        #[Language('XPath')]
        $xpath
    ): bool {
        return $this->findElement($xpath)->isSelected();
    }

    /**
     * {@inheritdoc}
     * @throws DriverException
     * @throws NoSuchElementException
     */
    public function selectOption(
        #[Language('XPath')]
        $xpath,
        $value,
        $multiple = false
    ): void {
        $element = $this->findElement($xpath);
        $tagName = strtolower($element->getTagName() ?? '');

        if ('input' === $tagName && 'radio' === strtolower($element->getAttribute('type') ?? '')) {
            $this->selectRadioValue($element, $value);

            return;
        }

        if ('select' === $tagName) {
            $this->selectOptionOnElement($element, $value, $multiple);

            return;
        }

        $message = 'Impossible to select an option on the element with XPath "%s" as it is not a select or radio input';
        throw new DriverException(sprintf($message, $xpath));
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function isSelected(
        #[Language('XPath')]
        $xpath
    ): bool {
        return $this->findElement($xpath)->isSelected();
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function click(
        #[Language('XPath')]
        $xpath
    ): void {
        $this->clickOnElement($this->findElement($xpath));
    }

    private function clickOnElement(RemoteWebElement $element): void
    {
        $element->getLocationOnScreenOnceScrolledIntoView();
        $element->click();
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function doubleClick(
        #[Language('XPath')]
        $xpath
    ): void {
        $this->doubleClickOnElement($this->findElement($xpath));
    }

    private function doubleClickOnElement(RemoteWebElement $element): void
    {
        $element->getLocationOnScreenOnceScrolledIntoView();
        $this->webDriver->getMouse()->doubleClick($element->getCoordinates());
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function rightClick(
        #[Language('XPath')]
        $xpath
    ): void {
        $this->rightClickOnElement($this->findElement($xpath));
    }

    /**
     * {@inheritdoc}
     * @throws DriverException
     * @throws NoSuchElementException
     */
    public function attachFile(
        #[Language('XPath')]
        $xpath,
        #[Language('file-reference')]
        $path
    ): void {
        $element = $this->findElement($xpath);
        $this->ensureInputType($element, $xpath, 'file', 'attach a file on');

        // @todo - Check if this is the correct way to upload files
        $element->sendKeys($path);
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function isVisible(
        #[Language('XPath')]
        $xpath
    ): bool {
        return $this->findElement($xpath)->isDisplayed();
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function mouseOver(
        #[Language('XPath')]
        $xpath
    ): void {
        $this->mouseOverElement($this->findElement($xpath));
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function focus(
        #[Language('XPath')]
        $xpath
    ): void {
        $this->trigger($xpath, 'focus');
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function blur(
        #[Language('XPath')]
        $xpath
    ): void {
        $this->trigger($xpath, 'blur');
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     * @throws NoSuchElementException
     */
    public function keyPress(
        #[Language('XPath')]
        $xpath,
        $char,
        $modifier = null
    ): void {
        $options = $this->charToSynOptions($char, $modifier);
        $this->trigger($xpath, 'keypress', $options);
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     * @throws NoSuchElementException
     */
    public function keyDown(
        #[Language('XPath')]
        $xpath,
        $char,
        $modifier = null
    ): void {
        $options = $this->charToSynOptions($char, $modifier);
        $this->trigger($xpath, 'keydown', $options);
    }

    /**
     * {@inheritdoc}
     * @throws JsonException
     * @throws NoSuchElementException
     */
    public function keyUp(
        #[Language('XPath')]
        $xpath,
        $char,
        $modifier = null
    ): void {
        $options = $this->charToSynOptions($char, $modifier);
        $this->trigger($xpath, 'keyup', $options);
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function dragTo(
        #[Language('XPath')]
        $sourceXpath,
        #[Language('XPath')]
        $destinationXpath
    ): void {
        $source = $this->findElement($sourceXpath);
        $destination = $this->findElement($destinationXpath);
        $this->webDriver->action()->dragAndDrop($source, $destination)->perform();
    }

    /**
     * {@inheritdoc}
     */
    public function executeScript(
        #[Language('JavaScript')]
        $script
    ): void {
        if (preg_match('/^function[\s(]/', $script ?? '')) {
            $script = preg_replace('/;$/', '', $script ?? '');
            $script = '(' . $script . ')';
        }

        $this->webDriver->executeScript($script);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluateScript(
        #[Language('JavaScript')]
        $script
    ) {
        if (strncmp(ltrim((string)$script), 'return ', 7) !== 0) {
            $script = "return $script;";
        }

        return $this->webDriver->executeScript($script);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(
        $timeout,
        #[Language('JavaScript')]
        $condition
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
     * @throws DriverException
     */
    public function resizeWindow($width, $height, $name = null): void
    {
        $this->withWindow(
            $name,
            fn() => $this
                ->webDriver
                ->manage()
                ->window()
                ->setSize(new WebDriverDimension($width, $height))
        );
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchElementException
     */
    public function submitForm(
        #[Language('XPath')]
        $xpath
    ): void {
        $this->findElement($xpath)->submit();
    }

    /**
     * {@inheritdoc}
     * @throws DriverException
     */
    public function maximizeWindow($name = null): void
    {
        $this->withWindow(
            $name,
            fn() => $this
                ->webDriver
                ->manage()
                ->window()
                ->maximize()
        );
    }

    // </editor-fold>

    // <editor-fold desc="Extra Public API">

    /**
     * @api
     */
    public function getBrowserName(): string
    {
        return $this->browserName;
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
     * @api
     */
    public function globalKeyPress($char, $modifier = null): void
    {
        $keyboard = $this->webDriver->getKeyboard();
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
     * @throws NoSuchElementException
     * @api
     */
    public function dragBy(
        #[Language('XPath')]
            $sourceXpath,
        int $xOffset,
        int $yOffset
    ): void {
        $source = $this->findElement($sourceXpath);
        $this->webDriver->action()->dragAndDropBy($source, $xOffset, $yOffset)->perform();
    }

    /**
     * Returns Session ID of WebDriver or `null`, when session not started yet.
     *
     * @return string|null
     * @api
     */
    public function getWebDriverSessionId(): ?string
    {
        return $this->isStarted()
            ? $this->webDriver->getSessionID()
            : null;
    }

    // </editor-fold>

    // <editor-fold desc="Private Utilities">

    /**
     * Detect and assign appropriate browser capabilities
     *
     * @see https://github.com/SeleniumHQ/selenium/wiki/DesiredCapabilities
     */
    private function initCapabilities(array $desiredCapabilities = []): DesiredCapabilities
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

    private function withSyn(): self
    {
        $hasSyn = $this->evaluateScript(
            'return window.syn !== undefined && window.syn.trigger !== undefined'
        );

        if (!$hasSyn) {
            $synJs = file_get_contents(__DIR__ . '/../resources/syn.js');
            $this->webDriver->executeScript($synJs);
        }

        return $this;
    }

    /**
     * @param int|string $char
     * @throws JsonException
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

        return json_encode($options, JSON_THROW_ON_ERROR);
    }

    /**
     * Executes JS on a given element - pass in a js script string and argument[0] will
     * be replaced with a reference to the result of the $xpath query
     *
     * @param string $xpath the xpath to search with
     * @param string $script the script to execute
     *
     * @return mixed
     * @throws NoSuchElementException
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
     * @example $this->executeJsOnXpath($xpath, 'return argument[0].childNodes.length');
     */
    private function executeJsOnElement(
        RemoteWebElement $element,
        #[Language('JavaScript')]
        string           $script
    ) {
        return $this->webDriver->executeScript($script, [$element]);
    }

    /**
     * @throws DriverException
     */
    private function applyTimeouts(): void
    {
        try {
            $timeouts = $this->webDriver->manage()->timeouts();
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

        $origWindowHandle = $this->webDriver->getWindowHandle();

        try {
            foreach ($this->webDriver->getWindowHandles() as $handle) {
                $this->webDriver->switchTo()->window($handle);
                if ($this->evaluateScript('window.name') === $name) {
                    return $handle;
                }
            }

            throw new DriverException("Could not find handle of window named \"$name\"");
        } finally {
            $this->webDriver->switchTo()->window($origWindowHandle);
        }
    }

    private function rightClickOnElement(RemoteWebElement $element): void
    {
        $element->getLocationOnScreenOnceScrolledIntoView();
        $this->webDriver->getMouse()->contextClick($element->getCoordinates());
    }

    private function mouseOverElement(RemoteWebElement $element): void
    {
        $element->getLocationOnScreenOnceScrolledIntoView();
        $this->webDriver->getMouse()->mouseMove($element->getCoordinates());
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
     * @throws NoSuchElementException
     */
    private function findElement(
        #[Language('XPath')]
        string           $xpath,
        RemoteWebElement $parent = null
    ): RemoteWebElement {
        $finder = WebDriverBy::xpath($xpath);
        return $parent
            ? $parent->findElement($finder)
            : $this->webDriver->findElement($finder);
    }

    /**
     * @throws DriverException
     */
    private function selectRadioValue(RemoteWebElement $element, string $value): void
    {
        // short-circuit when we already have the right button of the group to avoid XPath queries
        if ($element->getAttribute('value') === $value) {
            $element->click();

            return;
        }

        $name = $element->getAttribute('name');

        if (!$name) {
            throw new DriverException(sprintf('The radio button does not have the value "%s"', $value));
        }

        $formId = $element->getAttribute('form');

        try {
            $escapedName = $this->xpathEscaper->escapeLiteral($name);
            $escapedValue = $this->xpathEscaper->escapeLiteral($value);
            if (null !== $formId) {
                $escapedFormId = $this->xpathEscaper->escapeLiteral($formId);
                $input = $this->findElement(
                    <<<"XPATH"
                    //form[@id=$escapedFormId]//input[@type="radio" and not(@form) and @name=$escapedName and @value=$escapedValue]
                    |
                    //input[@type="radio" and @form=$escapedFormId and @name=$escapedName and @value=$escapedValue]
                    XPATH
                );
            } else {
                $input = $this->findElement(
                    "./ancestor::form//input[@type=\"radio\" and not(@form) and @name=$escapedName and @value=$escapedValue]",
                    $element
                );
            }
        } catch (NoSuchElementException $e) {
            $message = sprintf('The radio group "%s" does not have an option "%s"', $name, $value);

            throw new DriverException($message, 0, $e);
        }

        $input->click();
    }

    /**
     * @throws NoSuchElementException
     */
    private function selectOptionOnElement(RemoteWebElement $element, string $value, bool $multiple = false): void
    {
        $escapedValue = $this->xpathEscaper->escapeLiteral($value);
        // The value of an option is the normalized version of its text when it has no value attribute
        $optionQuery = sprintf(
            './/option[@value = %s or (not(@value) and normalize-space(.) = %s)]',
            $escapedValue,
            $escapedValue
        );
        $option = $this->findElement($optionQuery, $element); // Avoids selecting values from other select boxes

        if ($multiple || !$element->getAttribute('multiple')) {
            if (!$option->isSelected()) {
                $option->click();
            }

            return;
        }

        // Deselect all options before selecting the new one
        $this->deselectAllOptions($element);
        $option->click();
    }

    /**
     * Deselects all options of a multiple select
     *
     * Note: this implementation does not trigger a change event after deselecting the elements.
     *
     * @param RemoteWebElement $element
     */
    private function deselectAllOptions(RemoteWebElement $element): void
    {
        $script = <<<JS
            var node = arguments[0];
            var i, l = node.options.length;
            for (i = 0; i < l; i++) {
                node.options[i].selected = false;
            }
            JS;

        $this->executeJsOnElement($element, $script);
    }

    /**
     * @throws DriverException
     */
    private function ensureInputType(
        RemoteWebElement $element,
        #[Language('XPath')]
        string           $xpath,
        string           $type,
        string           $action
    ): void {
        if ($element->getTagName() !== 'input' || $element->getAttribute('type') !== $type) {
            throw new DriverException(
                "Impossible to $action the element with XPath \"$xpath\" as it is not a $type input"
            );
        }
    }

    /**
     * @throws NoSuchElementException
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

    // </editor-fold>
}
