<?php

/*
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mink\WebdriverClassDriver;

use Behat\Mink\Driver\CoreDriver;

class WebdriverClassicDriver extends CoreDriver
{
    public function isStarted(): bool
    {
        return false;
    }
}
