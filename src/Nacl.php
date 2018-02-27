<?php

namespace Nuglif\Nacl;

class Nacl
{
    public static function createParser()
    {
        $parser = new Parser;
        $parser->registerMacro(new Macros\File);
        $parser->registerMacro(new Macros\Env);
        $parser->registerMacro(new Macros\Constant);

        return $parser;
    }

    public static function parse($str)
    {
        return self::createParser()->parse($str);
    }

    public static function parseFile($file)
    {
        return self::createParser()->parseFile($file);
    }

    public static function dump($var)
    {
        return (new Dumper(
            Dumper::PRETTY_PRINT |
            Dumper::SHORT_SINGLE_ELEMENT
        ))->dump($var);
    }
}
