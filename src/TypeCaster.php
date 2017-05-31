<?php

namespace Nuglif\Nacl;

class TypeCaster
{
    public static function toNum($val)
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
                case 'm':
                    $res *= 1000;
                case 'k':
                    $res *= 1000;
                    break;
                case 'gb':
                    $res *= 1024;
                case 'mb':
                    $res *= 1024;
                case 'kb':
                    $res *= 1024;
                    break;
                case 'y':
                    $res *= 60 * 60 * 24 * 365;
                    break;
                case 'w':
                    $res *= 7;
                case 'd':
                    $res *= 24;
                case 'h':
                    $res *= 60;
                case 'min':
                    $res *= 60;
                    break;
                case 'ms':
                    $res /= 1000;
            }
        }

        return $res;
    }

    public static function toBool($val)
    {
        $val = strtolower($val);

        return 'true' === $val || 'yes' === $val || 'on' === $val;
    }
}
