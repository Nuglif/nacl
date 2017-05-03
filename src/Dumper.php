<?php

namespace Nuglif\Nacl;

class Dumper
{
    const STRING_DELIM  = '"';
    const NEW_LINE      = "\n";
    const LINE_END      = ";\n";

    public function dump($var)
    {
        return $this->internalDump($var);
    }

    private function internalDump($var, $level = 0, $root = true)
    {
        $indent      = '  ';
        $out         = '';

        $varType = gettype($var);

        switch ($varType) {
            case 'array':
                $out .= $root ? '' : '{' . self::NEW_LINE;
                foreach ($var as $key => $value) {
                    if (is_string($key)) {
                        $key = $this->safeString($key);
                    }

                    $str_value = $this->internalDump($value, $level + 1, false);
                    $out .= str_repeat($indent, $level) . $key . ' ' . $str_value . (is_array($value) ? self::NEW_LINE : self::LINE_END);
                }
                $out .= $root ? '' : str_repeat($indent, $level - 1) . '}';
                break;

            case 'string':
                $out .= $this->safeString($var);
                break;

            case 'integer':
            case 'double':
            case 'boolean':
                $out = var_export($var, true);
                break;
            case 'NULL':
                $out = 'null';
                break;

            default:
                throw \InvalidArgumentException("Unable to dump '${varType}' to NACL");
        }

        return $out;
    }

    private function safeString($var)
    {
        if (preg_match('#^(' . Lexer::REGEX_NAME . ')$#A', $var)) {
            switch ($var) {
                case 'true':
                case 'false':
                case 'on':
                case 'off':
                case 'yes':
                case 'no':
                case 'null':
                    return self::STRING_DELIM . $var . self::STRING_DELIM;
                default:
                    return $var;
             }
        }

        $find        = array(null, '\\', '"');
        $replace     = array('NULL', '\\\\', '\\"');

        for ($i = 0, $c = count($find); $i < $c; $i++) {
            $var = str_replace($find[$i], $replace[$i], $var);
        }

        return self::STRING_DELIM . $var . self::STRING_DELIM;
    }
}
