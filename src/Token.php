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
    public const T_NAME         = 256;
    public const T_NUM          = 257;
    public const T_STRING       = 258;
    public const T_BOOL         = 259;
    public const T_NULL         = 260;
    public const T_ENCAPSED_VAR = 261;
    public const T_VAR          = 262;
    public const T_END_STR      = 263;
    public const T_EOF          = -1;

    public int|string $type;
    public mixed $value;

    public function __construct(int|string $type, mixed $value)
    {
        $this->type  = $type;
        $this->value = $value;
    }

    public static function getLiteral(int|string $type): string
    {
        $refClass = new \ReflectionClass(self::class);
        $names    = array_flip($refClass->getConstants());

        if (isset($names[$type])) {
            return $names[$type];
        }

        assert(is_string($type));
        return $type;
    }
}
