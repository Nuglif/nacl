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
        $this->lexer->push($str, $filename);
        $this->nextToken();

        if ('[' == $this->token->type) {
            $result = $this->parseArray();
        } else {
            $result = $this->parseObject();
        }

        $this->consume(Token::T_EOF);
        $this->lexer->pop();

        return $result;
    }

    public function parseFile($file)
    {
        $filename = realpath($file);
        if (!$filename) {
            throw new \InvalidArgumentException('File not found: ' . $file);
        }

        return $this->parse(file_get_contents($file), $filename);
    }

    /**
     * Object       ::= "{" InnerObject "}" | InnerObject
     * InnerObject  ::= [ KeyValueList [ "," | ";" ] ]
     * KeyValueList ::= KeyValue [ [ "," | ";" ] KeyValueList ]
     * KeyValue     ::= ( ( T_END_STR | T_NAME | T_VAR ) [ ":" | "=" ] Value ) | MacroCall
     */
    private function parseObject()
    {
        $object = [];
        $opened = $this->consumeOptional('{');

        do {
            $name     = null;
            $continue = false;
            switch ($this->token->type) {
                case Token::T_END_STR:
                    $name = $this->parseString();
                case Token::T_NAME:
                    if (null === $name) {
                        $name = $this->token->value;
                        $this->nextToken();
                    }
                    $this->consumeOptional(':') || $this->consumeOptional('=');
                    $val           = $this->parseValue();
                    if (is_array($val) && isset($object[$name]) && is_array($object[$name])) {
                        $object[$name] = $this->deepMerge($object[$name], $val);
                    } else {
                        $object[$name] = $val;
                    }
                    $separator     = $this->consumeOptional(',') || $this->consumeOptional(';');
                    $continue      = is_array($val) || $separator;
                    break;
                case Token::T_VAR:
                    $name = $this->token->value;
                    $this->nextToken();
                    $this->consumeOptional(':') || $this->consumeOptional('=');
                    $val           = $this->parseValue();
                    $this->setVariable($name, $val);
                    $continue      = $this->consumeOptional(',') || $this->consumeOptional(';');
                    break;
                case '.':
                    $val      = $this->parseMacro($object);
                    $continue = $this->consumeOptional(';');
                    break;
            }
        } while ($continue);

        if ($opened) {
            $this->consume('}');
        }

        return $object;
    }

    /**
     * Array     ::= "[" [ ValueList ] "]"
     * ValueList ::= Value [ "," ValueList ]
     */
    private function parseArray()
    {
        $array = [];
        $this->consume('[');

        $continue = true;
        while ($continue && ']' !== $this->token->type) {
            $array[]  = $this->parseValue();
            $continue = $this->consumeOptional(',');
        }

        $this->consume(']');

        return $array;
    }

    /**
     * Value ::= String | Scalar | Variable | "{" InnerObject "}" | Array | MacroCall
     */
    private function parseValue()
    {
        switch ($this->token->type) {
            case Token::T_STRING:
            case Token::T_ENCAPSED_VAR:
                $value = $this->parseString();
                break;
            case Token::T_END_STR;
            case Token::T_NAME:
            case Token::T_BOOL:
            case Token::T_NUM:
            case Token::T_NULL:
                $value = $this->parseScalar();
                break;
            case Token::T_VAR:
                $value = $this->getVariable($this->token->value);
                $this->nextToken();
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
                $this->syntaxError();
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
    private function getVariable($name)
    {
        if (!isset($this->variables[$name])) {
            trigger_error('Undefined variable ' . $name);

            return '';
        }

        return $this->variables[$name];
    }

    /**
     * MacroCall ::= "." T_NAME [ "(" [ Object ] ")" ] Value
     */
    private function parseMacro(array &$context = null)
    {
        $this->consume('.');
        $result = null;

        if ($this->token->type != Token::T_NAME) {
            $this->syntaxError();
        }

        $name = $this->token->value;
        $this->nextToken();

        if ($this->consumeOptional('(')) {
            $options = $this->parseObject();
            $this->consume(')');
        } else {
            $options = [];
        }

        $param = $this->parseValue();

        switch ($name) {
            case 'include':
                $result = $this->doInclude($param, $options, $context);
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

    private function doInclude($file, $options, array &$context = null)
    {
        $options = array_merge([
            'required' => true,
        ], $options);

        $cwd = getcwd();
        if (file_exists($this->lexer->getFilename())) {
            chdir(dirname($this->lexer->getFilename()));
        }
        if (!$path = realpath($file)) {
            if ($options['required']) {
                $this->error('Unable to include file \'' . $file . '\'');
            }

            return null;
        }
        chdir($cwd);

        $token = $this->token;
        $this->lexer->push(file_get_contents($path), $path);
        $this->nextToken();
        $value = $this->parseObject();
        if (is_array($value) && is_array($context)) {
            $context = $this->deepMerge($context, $value);
        }
        $this->consume(Token::T_EOF);
        $this->lexer->pop();
        $this->token = $token;

        return $value;
    }

    private function consume($type)
    {
        if ($type !== $this->token->type) {
            $this->syntaxError();
        }

        $this->nextToken();
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
        $value   = (strlen($this->token->value) > 10) ? substr($this->token->value, 0, 10) . '...' : $this->token->value;

        $message = 'Syntax error, unexpected \'' . $value . '\'';
        if ($literal !== $value) {
            $message .= ' (' . $literal . ')';
        }
        $this->error($message);
    }

    private function error($message)
    {
        throw new ParsingException($message, $this->lexer->getFilename(), $this->lexer->getLine());
    }
}
