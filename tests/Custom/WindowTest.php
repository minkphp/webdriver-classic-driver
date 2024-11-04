<?php

namespace Mink\WebdriverClassicDriver\Tests\Custom;

use Behat\Mink\Tests\Driver\TestCase;

final class WindowTest extends TestCase
{
    public function testWindow(): void
    {
        $this->getSession()->visit($this->pathTo('/window.html'));
        $session = $this->getSession();
        $page = $session->getPage();
        $webAssert = $this->getAssertSession();

        $page->clickLink('Popup #1');
        $session->switchToWindow(null);

        $page->clickLink('Popup #2');
        $session->switchToWindow(null);

        $el = $webAssert->elementExists('css', '#text');
        $this->assertSame('Main window div text', $el->getText());

        $session->switchToWindow('popup_1');
        $el = $webAssert->elementExists('css', '#text');
        $this->assertSame('Popup#1 div text', $el->getText());

        $session->switchToWindow('popup_2');
        $el = $webAssert->elementExists('css', '#text');
        $this->assertSame('Popup#2 div text', $el->getText());

        $session->switchToWindow(null);
        $el = $webAssert->elementExists('css', '#text');
        $this->assertSame('Main window div text', $el->getText());
    }

    public function testWindowName(): void
    {
        $this->getSession()->visit($this->pathTo('/window.html'));
        $windowNames = $this->getSession()->getWindowNames();
        $this->assertArrayHasKey(0, $windowNames);

        $windowName = $this->getSession()->getWindowName();

        $this->assertIsString($windowName);
        $this->assertContains($windowName, $windowNames, 'The current window name should be one of the available window names.');
    }

    public function testGetWindowNames(): void
    {
        file_put_contents('php://stdout', "### 1 ###\n", FILE_APPEND);
        $this->getSession()->visit($this->pathTo('/window.html'));
        file_put_contents('php://stdout', "### 2 ###\n", FILE_APPEND);
        $session = $this->getSession();
        file_put_contents('php://stdout', "### 3 ###\n", FILE_APPEND);
        $page = $session->getPage();
        file_put_contents('php://stdout', "### 4 ###\n", FILE_APPEND);
        $windowName = $this->getSession()->getWindowName();
        file_put_contents('php://stdout', "### 5 ###\n", FILE_APPEND);
        $this->assertNotNull($windowName);
        file_put_contents('php://stdout', "### 6 ###\n", FILE_APPEND);
        $page->clickLink('Popup #1');
        file_put_contents('php://stdout', "### 7 ###\n", FILE_APPEND);
        $page->clickLink('Popup #2');
        file_put_contents('php://stdout', "### 8 ###\n", FILE_APPEND);
        $windowNames = $this->getSession()->getWindowNames();
        file_put_contents('php://stdout', "### 9 ###\n", FILE_APPEND);
        $this->assertNotNull($windowNames[0]);
        file_put_contents('php://stdout', "### 10 ###\n", FILE_APPEND);
        $this->assertNotNull($windowNames[1]);
        file_put_contents('php://stdout', "### 11 ###\n", FILE_APPEND);
        $this->assertNotNull($windowNames[2]);
        file_put_contents('php://stdout', "### 12 ###\n", FILE_APPEND);
    }

    public function testResizeWindow(): void
    {
        $this->getSession()->visit($this->pathTo('/index.html'));
        $session = $this->getSession();
        $expectedWidth = 640;
        $expectedHeight = 480;

        $session->resizeWindow($expectedWidth, $expectedHeight);
        $session->wait(1000, 'false');

        $jsWindowSizeScript = <<<"JS"
        (function () {
            var check = function (actualWidth, actualHeight) {
                    return Math.abs(actualWidth - $expectedWidth) <= 100
                        && Math.abs(actualHeight - $expectedHeight) <= 100;
                    },
                htmlElem = document.documentElement,
                bodyElem = document.getElementsByTagName('body')[0];

            return check(window.outerWidth, window.outerHeight)
                || check(
                    window.innerWidth || htmlElem.clientWidth || bodyElem.clientWidth,
                    window.innerHeight || htmlElem.clientHeight || bodyElem.clientHeight
                );
        })();
JS;
        $this->assertTrue($session->evaluateScript($jsWindowSizeScript));
    }

    public function testWindowMaximize(): void
    {
        $this->getSession()->visit($this->pathTo('/index.html'));
        $session = $this->getSession();

        $session->maximizeWindow();
        $session->wait(1000, 'false');

        $script = 'return Math.abs(screen.availHeight - window.outerHeight);';

        $this->assertLessThanOrEqual(100, $session->evaluateScript($script));
    }
}
