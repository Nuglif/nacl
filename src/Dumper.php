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
    const PRETTY_PRINT               = 1 << 1;
    const SEPARATOR_AFTER_NON_SCALAR = 1 << 2;
    const SHORT_SINGLE_ELEMENT       = 1 << 3;
    const NO_TRAILING_SEPARATOR      = 1 << 4;
    const ROOT_BRACES                = 1 << 5;
    const QUOTE_STR                  = 1 << 6;

    /**
     * @var string
     */
    private $indentStr = '  ';

    /**
     * @var string
     */
    private $assign = ' ';

    /**
     * @var string
     */
    private $separator = ';';

    /**
     * @var string
     */
    private $listSeparator = ',';

    /**
     * @var int
     */
    private $depth = 0;

    /**
     * @var int
     */
    private $options;

    public function __construct($options = 0)
    {
        $this->options = $options;
    }

    public function setIndent($str)
    {
        $this->indentStr = $str;
    }

    public function setAssign($str)
    {
        $this->assign = $str;
    }

    public function setSeparator($str)
    {
        $this->separator = $str;
    }

    public function setListSeparator($str)
    {
        $this->listSeparator = $str;
    }

    public function dump($var)
    {
        return $this->dumpVar($var, !$this->hasOption(self::ROOT_BRACES));
    }

    private function dumpVar($var, $root = false)
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

    private function dumpArray(array $var, $root = false)
    {
        if ($this->isAssociativeArray($var)) {
            return $this->dumpAssociativeArray($var, $root);
        }

        return $this->dumpIndexedArray($var);
    }

    private function dumpAssociativeArray(array $var, $root = false)
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

    private function dumpIndexedArray(array $var)
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

    private function dumpString($var)
    {
        if (!$this->hasOption(self::QUOTE_STR) && preg_match('#^(' . Lexer::REGEX_NAME . ')$#A', $var)) {
            switch ($var) {
                case 'true':
                case 'false':
                case 'on':
                case 'off':
                case 'yes':
                case 'no':
                case 'null':
                    return '"' . $var . '"';
                default:
                    return $var;
            }
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

    private function isAssociativeArray(array $var)
    {
        $i = 0;
        foreach (array_keys($var) as $key) {
            if ($key !== $i++) {
                return true;
            }
        }

        return false;
    }

    private function eol()
    {
        return $this->hasOption(self::PRETTY_PRINT) ? PHP_EOL : '';
    }

    private function indent()
    {
        return $this->hasOption(self::PRETTY_PRINT) ? str_repeat($this->indentStr, $this->depth) : '';
    }

    private function hasOption($opt)
    {
        return $opt & $this->options;
    }
}
