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

class Nacl
{
    private static array $macros = [];

    public static function registerMacro(MacroInterface $macro): void
    {
        self::$macros[] = $macro;
    }

    public static function createParser(): Parser
    {
        $parser = new Parser();
        $parser->registerMacro(new Macros\Env());
        $parser->registerMacro(new Macros\Constant());

        foreach (self::$macros as $macro) {
            $parser->registerMacro($macro);
        }

        return $parser;
    }

    public static function parse(string $str): mixed
    {
        return self::createParser()->parse($str);
    }

    public static function parseFile(string $file): mixed
    {
        return self::createParser()->parseFile($file);
    }

    public static function dump(mixed $var): mixed
    {
        return (new Dumper(
            Dumper::PRETTY_PRINT | Dumper::SHORT_SINGLE_ELEMENT
        ))->dump($var);
    }
}
