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

class Dumper
{
    public const PRETTY_PRINT               = 1 << 1;
    public const SEPARATOR_AFTER_NON_SCALAR = 1 << 2;
    public const SHORT_SINGLE_ELEMENT       = 1 << 3;
    public const NO_TRAILING_SEPARATOR      = 1 << 4;
    public const ROOT_BRACES                = 1 << 5;
    public const QUOTE_STR                  = 1 << 6;

    private string $indentStr = '  ';

    private string $assign = ' ';

    private string $separator = ';';

    private string $listSeparator = ',';

    private int $depth = 0;

    private int $options;

    public function __construct(int $options = 0)
    {
        $this->options = $options;
    }

    public function setIndent(string $str): void
    {
        $this->indentStr = $str;
    }

    public function setAssign(string $str): void
    {
        $this->assign = $str;
    }

    public function setSeparator(string $str): void
    {
        $this->separator = $str;
    }

    public function setListSeparator(string $str): void
    {
        $this->listSeparator = $str;
    }

    public function dump(mixed $var): string
    {
        return $this->dumpVar($var, !$this->hasOption(self::ROOT_BRACES));
    }

    private function dumpVar(mixed $var, bool $root = false): string
    {
        $varType = gettype($var);
        switch ($varType) {
            case 'array':
                return $this->dumpArray($var, $root);
            case 'string':
                return $this->dumpString($var);
            case 'integer':
            case 'double':
            case 'boolean':
                return var_export($var, true);
            case 'NULL':
                return 'null';
        }
    }

    private function dumpArray(array $var, bool $root = false): string
    {
        if ($this->isAssociativeArray($var)) {
            return $this->dumpAssociativeArray($var, $root);
        }

        return $this->dumpIndexedArray($var);
    }

    private function dumpAssociativeArray(array $var, bool $root = false): string
    {
        $inline = $this->hasOption(self::SHORT_SINGLE_ELEMENT) && (1 === count($var));
        $str    = '';

        if (!$root && !$inline) {
            $str .= '{' . $this->eol();
            ++$this->depth;
        }

        $remainingElements = count($var);

        foreach ($var as $key => $value) {
            --$remainingElements;

            $requireSep = !($this->hasOption(self::NO_TRAILING_SEPARATOR) && !$inline && !$remainingElements) && (
                !is_array($value) ||
                (
                    $this->hasOption(self::SEPARATOR_AFTER_NON_SCALAR) &&
                    (!$this->hasOption(self::SHORT_SINGLE_ELEMENT) || 1 !== count($value) || is_int(key($value)))
                )
            );

            $str .= ($inline ? '' : $this->indent())
                . $this->dumpString((string) $key)
                . $this->assign
                . $this->dumpVar($value)
                . ($requireSep ? $this->separator : '')
                . ($inline ? '' : $this->eol());
        }

        if (!$root && !$inline) {
            --$this->depth;
            $str .= $this->indent() . '}';
        }

        return $str;
    }

    private function dumpIndexedArray(array $var): string
    {
        $count = count($var);
        if (0 === $count) {
            return '[]';
        }

        $str = '[' . $this->eol();
        ++$this->depth;
        for ($i = 0; $i < $count; ++$i) {
            $str .= $this->indent() . rtrim((string) $this->dumpVar($var[$i]), $this->separator);
            if ($count - 1 !== $i) {
                $str .= $this->listSeparator;
            }
            $str .= $this->eol();
        }
        --$this->depth;
        $str .= $this->indent() . ']';

        return $str;
    }

    private function dumpString(string $var): string
    {
        if (!$this->hasOption(self::QUOTE_STR) && preg_match('#^(' . Lexer::REGEX_NAME . ')$#A', $var)) {
            return match ($var) {
                'true', 'false', 'on', 'off', 'yes', 'no', 'null' => '"' . $var . '"',
                default => $var,
            };
        }

        return '"' . strtr($var, [
            "\b" => '\\b',
            "\f" => '\\f',
            "\r" => '\\r',
            "\n" => '\\n',
            "\t" => '\\t',
            '"'  => '\\"',
            '\\' => '\\\\',
        ]) . '"';
    }

    private function isAssociativeArray(array $var): bool
    {
        $i = 0;
        foreach (array_keys($var) as $key) {
            if ($key !== $i++) {
                return true;
            }
        }

        return false;
    }

    private function eol(): string
    {
        return $this->hasOption(self::PRETTY_PRINT) ? PHP_EOL : '';
    }

    private function indent(): string
    {
        return $this->hasOption(self::PRETTY_PRINT) ? str_repeat($this->indentStr, $this->depth) : '';
    }

    private function hasOption($opt): bool
    {
        return (bool) ($opt & $this->options);
    }
}
