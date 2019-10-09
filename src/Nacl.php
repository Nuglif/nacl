<?php

namespace Nuglif\Nacl;

class Nacl
{
    private static $macros = [];

    public static function registerMacro(MacroInterface $macro)
    {
        self::$macros[] = $macro;
    }

    public static function createParser()
    {
        $parser = new Parser;
        $parser->registerMacro(new Macros\File);
        $parser->registerMacro(new Macros\Env);
        $parser->registerMacro(new Macros\Constant);

        foreach (self::$macros as $macro) {
            $parser->registerMacro($macro);
        }

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
            Dumper::PRETTY_PRINT | Dumper::SHORT_SINGLE_ELEMENT
        ))->dump($var);
    }
}
