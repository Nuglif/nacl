<?php
/**
 * This file is part of NACL.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright 2019 Nuglif (2018) Inc.
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author    Pierrick Charron <pierrick@adoy.net>
 * @author    Charle Demers <charle.demers@gmail.com>
 */

namespace Nuglif\Nacl\Macros;

use Nuglif\Nacl\MacroInterface;

class Callback implements MacroInterface
{
    private $name;
    private $callback;

    public function __construct($name, callable $callback)
    {
        $this->name     = $name;
        $this->callback = $callback;
    }

    public function getName()
    {
        return $this->name;
    }

    public function execute($parameter, array $options = [])
    {
        $callback = $this->callback;

        return $callback($parameter, $options);
    }
}
