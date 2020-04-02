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

namespace Nuglif\Nacl;

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
        if (isset($this->macro[$macro->getName()])) {
            throw new Exception('Macro with the same name already registered.');
        }

        $this->macro[$macro->getName()] = [ $macro, 'execute' ];
    }

    public function setVariable($name, $value)
    {
        $this->variables[$name] = $value;
    }

    public function parse($str, $filename = 'nacl string')
    {
        $result = $this->getAstFromString($str, $filename);

        return $result instanceof Node ? $result->getNativeValue() : $result;
    }

    /**
     * Nacl ::= RootValue | InnerObject
     */
    private function getAstFromString($str, $filename)
    {
        $this->lexer->push($str, $filename);
        $this->nextToken();

        if ('[' == $this->token->type) {
            $result = $this->parseArray();
        } elseif ('{' == $this->token->type) {
            $result = $this->parseObject();
        } else {
            $result = $this->parseRootValue(false, $found);
            if (!$found) {
                $result = $this->parseInnerObject();
            } else {
                $this->consumeOptionalSeparator();
                if ($result instanceof ObjectNode) {
                    $result = $this->parseInnerObject($result);
                }
            }
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
        } elseif (!is_file($filename)) {
            throw new \InvalidArgumentException($file . ' is not a file.');
        } elseif (!is_readable($filename)) {
            throw new \InvalidArgumentException($file . ' is not readable');
        }

        return $this->parse(file_get_contents($file), $filename);
    }

    /**
     * RootValue               ::= [ VariableAssignationList ] Value
     * VariableAssignationList ::= VariableAssignation [ Separator [ VariableAssignationList ] ]
     * VariableAssignation     ::= T_VAR OptionalAssignementOperator Value
     */
    private function parseRootValue($required = true, &$found = true)
    {
        $value = null;

        do {
            $found = true;
            $continue = false;
            switch ($this->token->type) {
                case Token::T_VAR:
                    $name = $this->token->value;
                    $this->nextToken();
                    $variableValueRequired = $this->consumeOptionalAssignementOperator();
                    if (!$variableValueRequired && $this->consumeOptionalSeparator()) {
                        $value = $this->getVariable($name);
                        break;
                    }
                    $variableValue = $this->parseValue($variableValueRequired, $variableValueFound);
                    if ($variableValueFound) {
                        $this->setVariable($name, $variableValue);
                        $continue = $this->consumeOptionalSeparator();
                        $found = false;
                        break;
                    }
                    $value = $this->getVariable($name);
                    break;
                default:
                    $value = $this->parseValue($required, $found);
                    if ($value instanceof MacroNode) {
                        $value = $value->execute();
                    }
            }
        } while ($continue);

        return $value;
    }

    /**
     * Object ::= "{" InnerObject "}"
     */
    private function parseObject()
    {
        $this->consume('{');
        $object = $this->parseInnerObject();
        $this->consume('}');

        return $object;
    }

    /**
     * InnerObject  ::= [ KeyValueList ]
     * KeyValueList ::= VariableAssignation|KeyValue [ Separator [ KeyValueList ] ]
     * KeyValue     ::= ( ( T_END_STR | T_NAME ) OptionalAssignementOperator Value ) | MacroCall
     */
    private function parseInnerObject(ObjectNode $object = null)
    {
        $object = $object ?: new ObjectNode;
        do {
            $name     = null;
            $continue = false;
            switch ($this->token->type) {
                case Token::T_END_STR:
                    $name = $this->parseString()->getNativeValue();
                    /* no break */
                case Token::T_NAME:
                    if (null === $name) {
                        $name = $this->token->value;
                        $this->nextToken();
                    }
                    $this->consumeOptionalAssignementOperator();
                    $val = $this->parseValue();
                    if ($val instanceof ObjectNode && isset($object[$name]) && $object[$name] instanceof ObjectNode) {
                        $object[$name] = $object[$name]->merge($val);
                    } else {
                        $object[$name] = $val;
                    }
                    $separator = $this->consumeOptionalSeparator();
                    $continue  = is_object($val) || $separator;
                    break;
                case Token::T_VAR:
                    $name = $this->token->value;
                    $this->nextToken();
                    $this->consumeOptionalAssignementOperator();
                    $val = $this->parseValue();
                    $this->setVariable($name, $val);
                    $continue = $this->consumeOptionalSeparator();
                    break;
                case '.':
                    $val = $this->parseMacro();
                    if ($val instanceof MacroNode) {
                        $val = $val->execute();
                    }
                    if (!$val instanceof ObjectNode) {
                        $this->error('Macro without assignation key must return an object');
                    }
                    $object   = $object->merge($val);
                    $continue = $this->consumeOptionalSeparator();
                    break;
            }
        } while ($continue);

        return $object;
    }

    /**
     * Array     ::= "[" [ ValueList ] "]"
     * ValueList ::= Value [ Separator [ ValueList ] ]
     */
    private function parseArray()
    {
        $array = new ArrayNode;
        $this->consume('[');

        $continue = true;
        while ($continue && ']' !== $this->token->type) {
            $array->add($this->parseValue());
            $continue = $this->consumeOptionalSeparator();
        }

        $this->consume(']');

        return $array;
    }

    /**
     * Value ::= {T_END_STR | T_NAME }* ( String | Scalar | MathExpr | Variable | Object | Array | MacroCall )
     */
    private function parseValue($required = true, &$found = true)
    {
        $value = null;
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
                    return new ObjectNode([ $value => $realValue ]);
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
                    $value = new OperationNode($value, $this->getVariable($this->token->value), OperationNode::CONCAT);
                    break;
                case Token::T_END_STR:
                    $continue = false;
                    /* no break */
                case Token::T_STRING:
                    $value = new OperationNode($value, $this->token->value, OperationNode::CONCAT);
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
     * MacroCall ::= "." T_NAME [ "(" InnerObject ")" ] Value
     */
    private function parseMacro()
    {
        $this->consume('.');
        $result = null;

        if (Token::T_NAME != $this->token->type) {
            $this->syntaxError();
        }

        $name = $this->token->value;
        $this->nextToken();

        if ($this->consumeOptional('(')) {
            $options = $this->parseInnerObject();
            $this->consume(')');
        } else {
            $options = new ObjectNode;
        }

        $param = $this->parseValue();

        switch ($name) {
            case 'include':
                $result = $this->doInclude($param, $options);
                break;
            case 'ref':
                $result = new ReferenceNode($param, $this->lexer->getFilename(), $this->lexer->getLine(), $options);
                break;
            case 'file':
                $result = $this->doIncludeFileContent($param, $options);
                break;
            default:
                if (!isset($this->macro[$name])) {
                    $this->error('Unknown macro \'' . $name . '\'');
                }
                $result = new MacroNode($this->macro[$name], $param, $options);
                break;
        }

        return $result;
    }

    private function doIncludeFileContent($fileName, $options)
    {
        if ($realpath = $this->resolvePath($fileName)) {
            return file_get_contents($realpath);
        }

        $options = $options->getNativeValue();
        if (array_key_exists('default', $options)) {
            return $options['default'];
        }

        $this->error("Unable to read file '${fileName}'");
    }

    private function doInclude($fileName, $options)
    {
        $includeValue = new ObjectNode;

        if (isset($options['glob']) ? $options['glob'] : false) {
            $files = $this->glob($fileName);
        } else {
            if (!$path = $this->resolvePath($fileName)) {
                if (isset($options['required']) ? $options['required'] : true) {
                    $this->error('Unable to include file \'' . $fileName . '\'');
                }

                return $includeValue;
            }

            $files = [ $path ];
        }

        $token = $this->token;
        $filenameKey = isset($options['filenameKey']) && $options['filenameKey'];

        foreach ($files as $file) {
            $value = $this->getAstFromString(file_get_contents($file), $file);

            if ($filenameKey) {
                $includeValue[pathinfo($file, PATHINFO_FILENAME)] = $value;
            } elseif ($value instanceof ObjectNode && $includeValue instanceof ObjectNode) {
                $includeValue = $includeValue->merge($value);
            } else {
                $includeValue = $value;
            }
        }

        $this->token = $token;

        return $includeValue;
    }

    public function resolvePath($file)
    {
        return $this->relativeToCurrentFile(function () use ($file) {
            return realpath($file);
        });
    }

    private function glob($pattern)
    {
        return $this->relativeToCurrentFile(function () use ($pattern) {
            return array_map('realpath', glob($pattern) ?: []);
        });
    }

    private function relativeToCurrentFile(callable $cb)
    {
        $cwd = getcwd();
        if (file_exists($this->lexer->getFilename())) {
            chdir(dirname($this->lexer->getFilename()));
        }
        $result = $cb();
        chdir($cwd);

        return $result;
    }

    /**
     * MathExpr ::= OrOperand { "|" OrOperand }*
     */
    private function parseMathExpr()
    {
        $value = $this->parseOrOperand();

        while ($this->consumeOptional('|')) {
            $value = new OperationNode($value, $this->parseOrOperand(), OperationNode::OR_OPERATOR);
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
            $value = new OperationNode($value, $this->parseAndOperand(), OperationNode::AND_OPERATOR);
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
                    $value = new OperationNode($value, $this->parseShiftOperand(), OperationNode::SHIFT_LEFT);
                    break;
                case '>>':
                    $this->nextToken();
                    $value = new OperationNode($value, $this->parseShiftOperand(), OperationNode::SHIFT_RIGHT);
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
                    $value = new OperationNode($value, $this->parseMathTerm(), OperationNode::ADD);
                    break;
                case '-':
                    $this->nextToken();
                    $value = new OperationNode($value, $this->parseMathTerm(), OperationNode::SUB);
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
                    $value = new OperationNode($value, $this->parseMathFactor(), OperationNode::MUL);
                    break;
                case '(':
                    $this->nextToken();
                    $value = new OperationNode($value, $this->parseMathExpr(), OperationNode::MUL);
                    $this->consume(')');
                    break;
                case '%':
                    $this->nextToken();
                    $value = new OperationNode($value, $this->parseMathFactor(), OperationNode::MOD);
                    break;
                case '/':
                    $this->nextToken();
                    $value = new OperationNode($value, $this->parseMathFactor(), OperationNode::DIV);
                    break;
                default:
                    $continue = false;
            }
        } while ($continue);

        return $value;
    }

    /**
     * MathFactor ::= (( "(" MathExpr ")" ) | T_NUM | T_VAR | ( ("+"|"-") MathTerm )) [ "^" MathFactor ]
     */
    private function parseMathFactor()
    {
        $value = null;
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
                $value = new OperationNode(0, $this->parseMathTerm(), OperationNode::SUB);
                break;
            default:
                $this->syntaxError();
        }

        if ($this->consumeOptional('^')) {
            $value = new OperationNode($value, $this->parseMathFactor(), OperationNode::POW);
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

    /**
     * Separator ::= [ ";" | "," ]
     */
    private function consumeOptionalSeparator()
    {
        if (',' !== $this->token->type && ';' !== $this->token->type) {
            return false;
        }

        $this->nextToken();

        return true;
    }

    /**
     * OptionalAssignementOperator ::= [ ":" | "=" ]
     */
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
