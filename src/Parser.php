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

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Parser
{
    private Lexer $lexer;
    private Token $token;
    private array $macro     = [];
    private array $variables = [];

    public function __construct(Lexer $lexer = null)
    {
        $this->lexer = $lexer ?: new Lexer();
    }

    public function registerMacro(MacroInterface $macro): void
    {
        if (isset($this->macro[$macro->getName()])) {
            throw new Exception('Macro with the same name already registered.');
        }

        $this->macro[$macro->getName()] = [ $macro, 'execute' ];
    }

    public function setVariable(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    public function parse(string $str, string $filename = 'nacl string'): mixed
    {
        $result = $this->getAstFromString($str, $filename);

        return $result instanceof Node ? $result->getNativeValue() : $result;
    }

    /**
     * Nacl ::= RootValue | InnerObject
     */
    private function getAstFromString(string $str, string $filename): mixed
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

    public function parseFile(string $file): mixed
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
    private function parseRootValue(bool $required = true, ?bool &$found = true): mixed
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
    private function parseObject(): ObjectNode
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
    private function parseInnerObject(ObjectNode $object = null): ObjectNode
    {
        $object = $object ?: new ObjectNode();
        do {
            $name     = null;
            $continue = false;
            switch ($this->token->type) {
                case Token::T_END_STR:
                    /** @psalm-suppress PossiblyInvalidMethodCall */
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
    private function parseArray(): ArrayNode
    {
        $array = new ArrayNode();
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
    private function parseValue(bool $required = true, ?bool &$found = true): mixed
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
                    return new ObjectNode([ (string) $value => $realValue ]);
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

                    return null;
                }
        }

        return $value;
    }

    /**
     * Scalar ::= T_END_STR | T_NAME | T_BOOL | T_NUM | T_NULL
     */
    private function parseScalar(): null|bool|int|float|string
    {
        $value = $this->token->value;
        $this->nextToken();

        return $value;
    }

    /**
     * String ::= { T_ENCAPSED_VAR | T_STRING }* T_END_STR
     */
    private function parseString(): string|OperationNode
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
    public function getVariable(string $name): mixed
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
    private function parseMacro(): mixed
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
            $options = new ObjectNode();
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
                assert(is_string($name));
                $result = new MacroNode($this->macro[$name], $param, $options);
                break;
        }

        return $result;
    }

    /**
     * @psalm-suppress InvalidReturnType
     */
    private function doIncludeFileContent(string $fileName, ObjectNode $options): mixed
    {
        if ($realpath = $this->resolvePath($fileName)) {
            return file_get_contents($realpath);
        }

        $options = $options->getNativeValue();
        if (array_key_exists('default', $options)) {
            return $options['default'];
        }

        $this->error("Unable to read file '{$fileName}'");
    }

    private function doInclude(Node|string $fileName, ObjectNode $options): mixed
    {
        $includeValue = new ObjectNode();

        $fileName = $fileName instanceof Node ? $fileName->getNativeValue() : $fileName;
        if ($options['glob'] ?? false) {
            $files = $this->glob($fileName);
        } else {
            if (!$path = $this->resolvePath($fileName)) {
                if ($options['required'] ?? true) {
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

    public function resolvePath(string $file): string|false
    {
        return $this->relativeToCurrentFile(fn() => realpath($file));
    }

    private function glob(string $pattern): array
    {
        return $this->relativeToCurrentFile(fn() => array_map('realpath', glob($pattern) ?: []));
    }

    private function relativeToCurrentFile(callable $cb): mixed
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
    private function parseMathExpr(): mixed
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
    private function parseOrOperand(): mixed
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
    private function parseAndOperand(): mixed
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
    private function parseShiftOperand(): mixed
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
    private function parseMathTerm(): mixed
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
    private function parseMathFactor(): mixed
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

    private function consume(int|string $type): void
    {
        if ($type !== $this->token->type) {
            $this->syntaxError();
        }

        $this->nextToken();
    }

    /**
     * Separator ::= [ ";" | "," ]
     */
    private function consumeOptionalSeparator(): bool
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
    private function consumeOptionalAssignementOperator(): bool
    {
        if (':' !== $this->token->type && '=' !== $this->token->type) {
            return false;
        }

        $this->nextToken();

        return true;
    }

    private function consumeOptional(int|string $type): bool
    {
        if ($type !== $this->token->type) {
            return false;
        }

        $this->nextToken();

        return true;
    }

    private function nextToken(): void
    {
        $this->token = $this->lexer->yylex();
    }

    private function syntaxError(): void
    {
        $literal = Token::getLiteral($this->token->type);
        $value   = (strlen((string) $this->token->value) > 10) ? substr($this->token->value, 0, 10) . '...' : $this->token->value;

        $message = 'Syntax error, unexpected \'' . $value . '\'';
        if ($literal !== $value) {
            $message .= ' (' . $literal . ')';
        }
        $this->error($message);
    }

    public function error(string $message): void
    {
        throw new ParsingException($message, $this->lexer->getFilename(), $this->lexer->getLine());
    }
}
