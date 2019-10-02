<?php

namespace Nuglif\Nacl;

class Parser
{
    private $lexer;
    private $token;
    private $macro      = [];
    private $variables  = [];

    public function __construct(Lexer $lexer = null)
    {
        $this->lexer = $lexer ?: new Lexer();
    }

    public function registerMacro(MacroInterface $macro)
    {
        if (isset($this->macro[$macro->getName()])) {
            throw new Exception('Macro with the same name already registered.');
        }

        if ($macro instanceof ParserAware) {
            $macro->setParser($this);
        }

        $this->macro[$macro->getName()] = [ $macro, 'execute' ];
    }

    public function setVariable($name, $value)
    {
        $this->variables[$name] = $value;
    }

    /**
     * Nacl ::= Array | Object
     */
    public function parse($str, $filename = 'nacl string')
    {
        $token = $this->token;
        $this->lexer->push($str, $filename);
        $this->nextToken();

        if ('[' == $this->token->type) {
            $result = $this->parseArray();
        } else {
            $result = $this->parseObject();
        }

        $this->consume(Token::T_EOF);
        $this->lexer->pop();
        $this->token = $token;

        return $result;
    }

    public function parseFile($file)
    {
        $filename = realpath($file);
        if (!$filename) {
            throw new \InvalidArgumentException('File not found: ' . $file);
        } elseif (!is_file($filename)) {
            throw new \InvalidArgumentException($file . ' is not a file.');
        } elseif (!is_readable($filename)) {
            throw new \InvalidArgumentException($file . ' is not readable');
        }

        return $this->parse(file_get_contents($file), $filename);
    }

    /**
     * Object       ::= "{" InnerObject "}" | InnerObject
     * InnerObject  ::= [ KeyValueList [ Separator ] ]
     * KeyValueList ::= KeyValue [ Separator KeyValueList ]
     * KeyValue     ::= ( ( T_END_STR | T_NAME | T_VAR ) [ ":" | "=" ] Value ) | MacroCall
     * Separator    ::= "," | ";"
     */
    private function parseObject()
    {
        $opened = $this->consumeOptional('{');
        $object = $this->parseInnerObject();
        if ($opened) {
            $this->consume('}');
        }

        return $object;
    }

    private function parseInnerObject()
    {
        $object = [];
        do {
            $name     = null;
            $continue = false;
            switch ($this->token->type) {
                case Token::T_END_STR:
                    $name = $this->parseString();
                    /* No break */
                case Token::T_NAME:
                    if (null === $name) {
                        $name = $this->token->value;
                        $this->nextToken();
                    }
                    $this->consumeOptionalAssignementOperator();
                    $val           = $this->parseValue();
                    if (is_array($val) && isset($object[$name]) && is_array($object[$name])) {
                        $object[$name] = $this->deepMerge($object[$name], $val);
                    } else {
                        $object[$name] = $val;
                    }
                    $separator     = $this->consumeOptionalSeparator();
                    $continue      = is_array($val) || $separator;
                    break;
                case Token::T_VAR:
                    $name = $this->token->value;
                    $this->nextToken();
                    $this->consumeOptionalAssignementOperator();
                    $val           = $this->parseValue();
                    $this->setVariable($name, $val);
                    $continue      = $this->consumeOptionalSeparator();
                    break;
                case '.':
                    $val      = $this->parseMacro($object);
                    if (!is_array($val)) {
                        $this->error('Macro without assignation key must return an object');
                    }
                    $object = $this->deepMerge($object, $val);
                    $continue      = $this->consumeOptionalSeparator();
                    break;
            }
        } while ($continue);

        return $object;
    }

    /**
     * Array     ::= "[" [ ValueList ] "]"
     * ValueList ::= Value [ Separator ValueList ]
     */
    private function parseArray()
    {
        $array = [];
        $this->consume('[');

        $continue = true;
        while ($continue && ']' !== $this->token->type) {
            $array[]  = $this->parseValue();
            $continue = $this->consumeOptionalSeparator();
        }

        $this->consume(']');

        return $array;
    }

    /**
     * Value ::= {T_END_STR | T_NAME }* ( String | Scalar | MathExpr | Variable | "{" InnerObject "}" | Array | MacroCall )
     */
    private function parseValue($required = true, &$found = true)
    {
        $found = true;
        switch ($this->token->type) {
            case Token::T_STRING:
            case Token::T_ENCAPSED_VAR:
                $value = $this->parseString();
                break;
            case Token::T_END_STR:
            case Token::T_NAME:
                $value     = $this->parseScalar();
                $required  = $this->consumeOptionalAssignementOperator();
                $realValue = $this->parseValue($required, $valueIsKey);
                if ($valueIsKey) {
                    return [ $value => $realValue ];
                }
                break;
            case Token::T_BOOL:
            case Token::T_NULL:
                $value = $this->parseScalar();
                break;
            case Token::T_NUM:
            case Token::T_VAR:
            case '+':
            case '-':
            case '(':
                $value = $this->parseMathExpr();
                break;
            case '{':
                $value = $this->parseObject();
                break;
            case '[':
                $value = $this->parseArray();
                break;
            case '.':
                $value = $this->parseMacro();
                break;
            default:
                if ($required) {
                    $this->syntaxError();
                } else {
                    $found = false;

                    return;
                }
        }

        return $value;
    }

    /**
     * Scalar ::= T_END_STR | T_NAME | T_BOOL | T_NUM | T_NULL
     */
    private function parseScalar()
    {
        $value = $this->token->value;
        $this->nextToken();

        return $value;
    }

    /**
     * String ::= { T_ENCAPSED_VAR | T_STRING }* T_END_STR
     */
    private function parseString()
    {
        $value    = '';
        $continue = true;

        do {
            switch ($this->token->type) {
                case Token::T_ENCAPSED_VAR:
                    $value .= $this->getVariable($this->token->value);
                    break;
                case Token::T_END_STR:
                    $continue = false;
                    /* no break */
                case Token::T_STRING:
                    $value .= $this->token->value;
                    break;
            }

            $this->nextToken();
        } while ($continue);

        return $value;
    }

    /**
     * Variable ::= T_VAR
     */
    public function getVariable($name)
    {
        switch ($name) {
            case '__FILE__':
                return realpath($this->lexer->getFilename());
            case '__DIR__':
                return dirname(realpath($this->lexer->getFilename()));
            default:
                if (!isset($this->variables[$name])) {
                    trigger_error('Undefined variable ' . $name);

                    return '';
                }

                return $this->variables[$name];
        }
    }

    /**
     * MacroCall ::= "." T_NAME [ "(" [ Object ] ")" ] Value
     */
    private function parseMacro()
    {
        $this->consume('.');
        $result = null;

        if ($this->token->type != Token::T_NAME) {
            $this->syntaxError();
        }

        $name = $this->token->value;
        $this->nextToken();

        if ($this->consumeOptional('(')) {
            $options = $this->parseInnerObject();
            $this->consume(')');
        } else {
            $options = [];
        }

        $param = $this->parseValue();

        switch ($name) {
            case 'include':
                $result = $this->doInclude($param, $options);
                break;
            default:
                if (!isset($this->macro[$name])) {
                    $this->error('Unknown macro \'' . $name . '\'');
                }
                $result = $this->macro[$name]($param, $options);
                break;
        }

        return $result;
    }

    private function deepMerge(array $a1, array $a2)
    {
        if (empty($a1)) {
            return $a2;
        } elseif (empty($a2)) {
            return $a1;
        }

        if (is_int(key($a1)) || is_int(key($a2))) {
            return $a2;
        }

        foreach ($a2 as $key => $value) {
            if (!isset($a1[$key]) || !is_array($a1[$key]) || !is_array($value)) {
                $a1[$key] = $value;
            } else {
                $a1[$key] = $this->deepMerge($a1[$key], $value);
            }
        }

        return $a1;
    }

    private function doInclude($file, $options)
    {
        $value = [];
        $options = array_merge([
            'required' => true,
            'glob'     => false,
        ], $options);

        if ($options['glob']) {
            $files = $this->glob($file);
        } else {
            if (!$path = $this->resolvePath($file)) {
                if ($options['required']) {
                    $this->error('Unable to include file \'' . $file . '\'');
                }

                return $value;
            }

            $files = [ $path ];
        }

        $token = $this->token;

        foreach ($files as $file) {
            $this->lexer->push(file_get_contents($file), $file);
            $this->nextToken();
            if ('[' == $this->token->type) {
                $value = $this->deepMerge($value, $this->parseArray());
            } else {
                $value = $this->deepMerge($value, $this->parseObject());
            }
            $this->consume(Token::T_EOF);
            $this->lexer->pop();
        }

        $this->token = $token;

        return $value;
    }

    public function resolvePath($file)
    {
        $cwd = getcwd();
        if (file_exists($this->lexer->getFilename())) {
            chdir(dirname($this->lexer->getFilename()));
        }
        $file = realpath($file);
        chdir($cwd);

        return $file;
    }

    private function glob($pattern)
    {
        $cwd = getcwd();
        if (file_exists($this->lexer->getFilename())) {
            chdir(dirname($this->lexer->getFilename()));
        }
        $files = array_map('realpath', glob($pattern));
        chdir($cwd);

        return $files;
    }

    /**
     * MathExpr ::= OrOperand { "|" OrOperand }*
     */
    private function parseMathExpr()
    {
        $value = $this->parseOrOperand();

        while ($this->consumeOptional('|')) {
            $value |= $this->parseOrOperand();
        }

        return $value;
    }

    /**
     * OrOperand ::= AndOperand { "&" AndOperand }*
     */
    private function parseOrOperand()
    {
        $value = $this->parseAndOperand();

        while ($this->consumeOptional('&')) {
            $value &= $this->parseAndOperand();
        }

        return $value;
    }

    /**
     * AndOperand ::= ShiftOperand { ( "<<" | ">>" ) ShiftOperand }*
     */
    private function parseAndOperand()
    {
        $value = $this->parseShiftOperand();

        $continue = true;
        do {
            switch ($this->token->type) {
                case '<<':
                    $this->nextToken();
                    $value <<= $this->parseShiftOperand();
                    break;
                case '>>':
                    $this->nextToken();
                    $value >>= $this->parseShiftOperand();
                    break;
                default:
                    $continue = false;
            }
        } while ($continue);

        return $value;
    }

    /**
     * ShiftOperand ::= MathTerm { ( "+" | "-" ) MathTerm }*
     */
    private function parseShiftOperand()
    {
        $value = $this->parseMathTerm();

        $continue = true;
        do {
            switch ($this->token->type) {
                case '+':
                    $this->nextToken();
                    $value += $this->parseMathTerm();
                    break;
                case '-':
                    $this->nextToken();
                    $value -= $this->parseMathTerm();
                    break;
                default:
                    $continue = false;
            }
        } while ($continue);

        return $value;
    }

    /**
     * MathTerm ::= MathFactor { ( ( "*" | "%" | "/" ) MathFactor ) | ( "(" MathExpr ")" ) }*
     */
    private function parseMathTerm()
    {
        $value = $this->parseMathFactor();

        $continue = true;
        do {
            switch ($this->token->type) {
                case '*':
                    $this->nextToken();
                    $value *= $this->parseMathFactor();
                    break;
                case '(':
                    $this->nextToken();
                    $value *= $this->parseMathExpr();
                    $this->consume(')');
                    break;
                case '%':
                    $this->nextToken();
                    $value %= $this->parseMathExpr();
                    break;
                case '/':
                    $this->nextToken();
                    $value /= $this->parseMathFactor();
                    break;
                default:
                    $continue = false;
            }
        } while ($continue);

        return $value;
    }

    /**
     * MathFactor ::= ( "(" MathExpr ")" ) | T_NUM | T_VAR | ( ("+"|"-") MathTerm ) [ "^" MathFactor ]
     */
    private function parseMathFactor()
    {
        switch ($this->token->type) {
            case '(':
                $this->nextToken();
                $value = $this->parseMathExpr();
                $this->consume(')');
                break;
            case Token::T_NUM:
                $value = $this->token->value;
                $this->nextToken();
                break;
            case Token::T_VAR:
                $value = $this->getVariable($this->token->value);
                $this->nextToken();
                break;
            case '+':
                $this->nextToken();
                $value = $this->parseMathTerm();
                break;
            case '-':
                $this->nextToken();
                $value = -$this->parseMathTerm();
                break;
            default:
                $this->syntaxError();
        }

        if ($this->consumeOptional('^')) {
            $value **= $this->parseMathFactor();
        }

        return $value;
    }

    private function consume($type)
    {
        if ($type !== $this->token->type) {
            $this->syntaxError();
        }

        $this->nextToken();
    }

    private function consumeOptionalSeparator()
    {
        if (',' !== $this->token->type && ';' !== $this->token->type) {
            return false;
        }

        $this->nextToken();

        return true;
    }

    private function consumeOptionalAssignementOperator()
    {
        if (':' !== $this->token->type && '=' !== $this->token->type) {
            return false;
        }

        $this->nextToken();

        return true;
    }

    private function consumeOptional($type)
    {
        if ($type !== $this->token->type) {
            return false;
        }

        $this->nextToken();

        return true;
    }

    private function nextToken()
    {
        $this->token = $this->lexer->yylex();
    }

    private function syntaxError()
    {
        $literal = Token::getLiteral($this->token->type);
        $value   = (strlen((string) $this->token->value) > 10) ? substr($this->token->value, 0, 10) . '...' : $this->token->value;

        $message = 'Syntax error, unexpected \'' . $value . '\'';
        if ($literal !== $value) {
            $message .= ' (' . $literal . ')';
        }
        $this->error($message);
    }

    public function error($message)
    {
        throw new ParsingException($message, $this->lexer->getFilename(), $this->lexer->getLine());
    }
}
