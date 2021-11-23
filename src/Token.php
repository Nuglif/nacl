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

namespace Nuglif\Nacl;

class Token
{
    const T_NAME         = 256;
    const T_NUM          = 257;
    const T_STRING       = 258;
    const T_BOOL         = 259;
    const T_NULL         = 260;
    const T_ENCAPSED_VAR = 261;
    const T_VAR          = 262;
    const T_END_STR      = 263;
    const T_EOF          = -1;

    public $type;
    public $value;

    public function __construct($type, $value)
    {
        $this->type  = $type;
        $this->value = $value;
    }

    public static function getLiteral($type)
    {
        $refClass = new \ReflectionClass(self::class);
        $names    = array_flip($refClass->getConstants());

        if (isset($names[$type])) {
            return $names[$type];
        }

        return $type;
    }
}
