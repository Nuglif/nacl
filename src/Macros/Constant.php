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

declare(strict_types=1);

namespace Nuglif\Nacl\Macros;

use Nuglif\Nacl\MacroInterface;

class Constant implements MacroInterface
{
    public function getName()
    {
        return 'const';
    }

    public function execute($parameter, array $options = [])
    {
        if (!is_string($parameter)) {
            throw new \InvalidArgumentException('Constant parameter must be a string');
        }

        if (!defined($parameter) && array_key_exists('default', $options)) {
            return $options['default'];
        }

        return constant($parameter);
    }
}
