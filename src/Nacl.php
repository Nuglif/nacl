<?php

namespace Nuglif\Nacl;

class Nacl
{
    public static function parse($str)
    {
        return (new Parser)->parse($str);
    }

    public static function parseFile($file)
    {
        return (new Parser)->parseFile($file);
    }

    public static function dump($var)
    {
        return (new Dumper)->dump($var);
    }
}
