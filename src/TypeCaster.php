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

class TypeCaster
{
    public static function toNum(string $val): float|int
    {
        $f = (float) $val;
        $i = (int) $val;
        if ($i == $f) {
            $res = $i;
        } else {
            $res = $f;
        }

        if (preg_match('/[^0-9]*$/', strtolower($val), $matches)) {
            switch ($matches[0]) {
                case 'g':
                    $res *= 1000;
                    /* no break */
                case 'm':
                    $res *= 1000;
                    /* no break */
                case 'k':
                    $res *= 1000;
                    break;
                case 'gb':
                    $res *= 1024;
                    /* no break */
                case 'mb':
                    $res *= 1024;
                    /* no break */
                case 'kb':
                    $res *= 1024;
                    break;
                case 'y':
                    $res *= 60 * 60 * 24 * 365;
                    break;
                case 'w':
                    $res *= 7;
                    /* no break */
                case 'd':
                    $res *= 24;
                    /* no break */
                case 'h':
                    $res *= 60;
                    /* no break */
                case 'min':
                    $res *= 60;
                    break;
                case 'ms':
                    $res /= 1000;
                    break;
            }
        }

        return $res;
    }

    public static function toBool(string $val): bool
    {
        $val = strtolower($val);

        return 'true' === $val || 'yes' === $val || 'on' === $val;
    }
}
