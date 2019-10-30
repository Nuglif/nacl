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
use Nuglif\Nacl\TypeCaster;

class Env implements MacroInterface
{
    public function getName()
    {
        return 'env';
    }

    public function execute($parameter, array $options = [])
    {
        $options = array_merge([
            'type' => 'string',
        ], $options);

        $val = getenv($parameter);

        if (false === $val) {
            return array_key_exists('default', $options) ? $options['default'] : false;
        }

        return $this->cast($val, $options['type']);
    }

    private function cast($value, $type)
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
                return TypeCaster::toBool($value);
            case 'int':
            case 'integer':
                return (int) TypeCaster::toNum($value);
            case 'num':
            case 'numeric':
                return TypeCaster::toNum($value);
            case 'str':
            case 'string':
                return $value;
            default:
                throw new \InvalidArgumentException('Unknown type: ' . $type);
        }
    }
}
