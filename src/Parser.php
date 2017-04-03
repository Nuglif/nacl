<?php

namespace Adoy\Nacl;

class Parser
{
    private $lexer;
    private $token;
    private $macro     = [];
    private $variables = [];

    public function __construct(Lexer $lexer = null)
    {
        $this->lexer = $lexer ?: new Lexer();
    }

    public function registerMacro(MacroInterface $macro)
    {
        $this->macro[$macro->getName()] = [ $macro, 'execute' ];
    }

    public function addVariable($name, $value)
    {
        $this->variables[$name] = $value;
    }

    public function parse($str, $filename = null)
    {
        $this->lexer->push($str, $filename);
        $this->nextToken();

        if ('[' == $this->token->type) {
            $result = $this->parseArray();
        } else {
            $result = $this->parseObject();
        }

        $this->consume(Token::T_EOF);

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
                    $object[$name] = $val;
                    $separator     = $this->consumeOptional(',') || $this->consumeOptional(';');
                    $continue      = is_array($val) || $separator;
                    break;
                case '.':
                    $this->nextToken();
                    $val = $this->parseMacro();
                    if (is_array($val)) {
                        $object = array_merge($object, $val);
                    } else {
                        // @todo warning
                    }
                    $continue = $this->consumeOptional(';');
                    break;
            }
        } while ($continue);

        if ($opened) {
            $this->consume('}');
        }

        return $object;
    }

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
                $value = $this->getSymbol($this->token->value);
                $this->nextToken();
                break;
            case '{':
                $value = $this->parseObject();
                break;
            case '[':
                $value = $this->parseArray();
                break;
            case '.':
                $this->nextToken();
                $value = $this->parseMacro();
                break;
            default:
                var_dump(__METHOD__, $this->token);
        }

        return $value;
    }

    private function parseScalar()
    {
        $value = $this->token->value;
        $this->nextToken();

        return $value;
    }

    private function parseString()
    {
        $value    = '';
        $continue = true;

        do {
            switch ($this->token->type) {
                case Token::T_ENCAPSED_VAR:
                    $value .= $this->getSymbol($this->token->value);
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

    private function getSymbol($name)
    {
        if (!isset($this->variables[$name])) {
            trigger_error('Undefined variable ' . $name);

            return '';
        }

        return $this->variables[$name];
    }

    private function parseMacro()
    {
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
                return $this->doInclude($param, $options);
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

    private function doInclude($file)
    {
        $path = dirname($this->lexer->getFilename()) . '/' . $file;

        $token = $this->token;
        $this->lexer->push(file_get_contents($path), realpath($path));
        $this->nextToken();
        $value = $this->parseObject();
        $this->consume(Token::T_EOF);
        $this->lexer->pop();
        $this->token = $token;

        return $value;
    }

    private function consume($type)
    {
        if ($type !== $this->token->type) {
            $this->syntaxError([$type]);
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

    private function syntaxError(array $expected = [])
    {
        $message = 'Syntax error, unexpected \'' . Token::getLiteral($this->token->type) . '\'';
        if (!empty($expected)) {
            $message .= (count($expected) > 1) ? ', expected on of ' : ', expected ';
            $message .= implode(',', array_map(Token::class . '::getLiteral', $expected));
        }
        $this->error($message);
    }

    private function error($message)
    {
        throw new ParsingException($message, $this->lexer->getFilename(), $this->lexer->getLine());
    }
}
