<?php

namespace Nuglif\Nacl;

class Lexer extends AbstractLexer
{
    const STATE_INITITAL  = 0;
    const STATE_INSTRING  = 1;
    const STATE_INHEREDOC = 2;

    const REGEX_SPACE       = '[ \t\n\r]+';
    const REGEX_COMMENT     = '(?://|\#).*';
    const REGEX_COMMENT_ML  = '/\*';
    const REGEX_NAME        = '[A-Za-z0-9_]+';
    const REGEX_VAR         = '?:\${([^}]+)}';
    const REGEX_NUM         = '[-+]?(?:[0-9]*\.?[0-9]+|[0-9]+\.)(?:E(?:\+|-)?[0-9]+)?(?:[kmg]b?|[s|h|min|d|w|y])?';
    const REGEX_DQUOTE      = '"';
    const REGEX_HEREDOC     = '?:<<<([A-Za-z0-9_]+)\n';
    const REGEX_BOOL        = 'true|false|yes|no|on|off';
    const REGEX_NULL        = 'null';
    const REGEX_TOKEN       = '[\[\]=:{};,.()]';
    const REGEX_ANY         = '.';

    private $textBuffer;
    private $_mb_convert_encoding;

    public function __construct(...$args)
    {
        $this->_mb_convert_encoding  = function_exists('mb_convert_encoding');
        parent::__construct(...$args);
    }

    protected function getRules()
    {
        return [
            self::STATE_INITITAL => [
                self::REGEX_SPACE      => false,
                self::REGEX_COMMENT    => false,
                self::REGEX_COMMENT_ML => function ($yylval) {
                    $pos = @strpos($this->content, '*/', $this->count + 2);
                    if (false === $pos) {
                        $this->line += substr_count(substr($this->content, $this->count), "\n");
                        $this->error('Unterminated multiline comment');
                    }
                    $this->line += substr_count(substr($this->content, $this->count, $pos - $this->count + 2), "\n");
                    $this->count = $pos + 2;
                },
                self::REGEX_DQUOTE  => function () {
                    $this->begin(self::STATE_INSTRING);
                    $this->textBuffer = '';
                },
                self::REGEX_BOOL    => function (&$yylval) {
                    $yylval = $this->stringToBool($yylval);

                    return Token::T_BOOL;
                },
                self::REGEX_NULL    => function (&$yylval) {
                    $yylval = null;

                    return Token::T_NULL;
                },
                self::REGEX_NUM     => function (&$yylval) {
                    $yylval = $this->stringToNum($yylval);

                    return Token::T_NUM;
                },
                self::REGEX_NAME    => function () {
                    return Token::T_NAME;
                },
                self::REGEX_TOKEN   => function ($yylval) {
                    return $yylval;
                },
                self::REGEX_HEREDOC => function (&$yylval) {
                    $needle = "\n" . $yylval;
                    $pos    = strpos($this->content, $needle, $this->count);
                    if (false === $pos) {
                        $this->line += substr_count(substr($this->content, $this->count), "\n");
                        $this->error('Unterminated HEREDOC');
                    }

                    $yylval = substr($this->content, $this->count, $pos - $this->count);
                    $this->line += substr_count($yylval, "\n") + 1;
                    $this->count += strlen($yylval) + strlen($needle);

                    return Token::T_END_STR;
                },
                self::REGEX_VAR => function () {
                    return Token::T_VAR;
                },
                self::REGEX_ANY     => function ($yylval) {
                    $this->error('Unexpected char \'' . $yylval . '\'');
                },
                self::EOF           => function () {
                    return Token::T_EOF;
                },
            ],
            self::STATE_INSTRING => [
                '[^\\\"$]+'          => function (&$yylval) {
                    $this->textBuffer .= $yylval;
                    if ('$' == substr($this->content, $this->count, 1)) {
                        $yylval = $this->textBuffer;
                        $this->textBuffer = '';

                        return Token::T_STRING;
                    }
                },
                '?:\\\(.)'          => function ($yylval) {
                    switch ($yylval) {
                        case 'n':
                            $this->textBuffer .= "\n";
                            break;
                        case 't':
                            $this->textBuffer .= "\t";
                            break;
                        case '\\':
                        case '/':
                        case '"':
                            $this->textBuffer .= $yylval;
                            break;
                        case 'u':
                            $utf8_code = substr($this->content, $this->count, 4);
                            if (preg_match('/[A-Fa-f0-9]{4,4}/', $utf8_code)) {
                                $utf16 = chr(hexdec($utf8_code[0] . $utf8_code[1])) . chr(hexdec($utf8_code[2] . $utf8_code[3]));
                                $this->textBuffer .= $this->utf16_to_utf8($utf16);
                                $this->count += 4;
                                break;
                            }
                            /* No break */
                        default:
                            $this->textBuffer .= '\\' . $yylval;
                            break;
                    }
                },
                self::REGEX_VAR => function ($yylval) {
                    return Token::T_ENCAPSED_VAR;
                },
                self::REGEX_DQUOTE  => function (&$yylval) {
                    $yylval = $this->textBuffer;
                    $this->begin(self::STATE_INITITAL);

                    return Token::T_END_STR;
                },
                self::EOF           => function () {
                    $this->error('Unterminated string');
                },
            ],
        ];
    }

    private function utf16_to_utf8($utf16)
    {
        if ($this->_mb_convert_encoding) {
            return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
        }

        $bytes = (ord($utf16{0}) << 8) | ord($utf16{1});

        switch (true) {
            case ((0x7F & $bytes) == $bytes):
                return chr(0x7F & $bytes);

            case (0x07FF & $bytes) == $bytes:
                return chr(0xC0 | (($bytes >> 6) & 0x1F))
                    . chr(0x80 | ($bytes & 0x3F));

            case (0xFFFF & $bytes) == $bytes:
                return chr(0xE0 | (($bytes >> 12) & 0x0F))
                    . chr(0x80 | (($bytes >> 6) & 0x3F))
                    . chr(0x80 | ($bytes & 0x3F));
        }

        return '';
    }

    private function stringToNum($val)
    {
        $f = (float) $val;
        $i = (int) $val;
        if ($i == $f) {
            $res = $i;
        } else {
            $res = $f;
        }

        if (preg_match('/(?:[kmg]b?|[h|min|d|w|y])$/', strtolower($val), $matches)) {
            switch ($matches[0]) {
                case 'g':
                    $res *= 1000;
                case 'm':
                    $res *= 1000;
                case 'k':
                    $res *= 1000;
                    break;
                case 'gb':
                    $res *= 1024;
                case 'mb':
                    $res *= 1024;
                case 'kb':
                    $res *= 1024;
                    break;
                case 'y':
                    $res *= 60 * 60 * 24 * 365;
                    break;
                case 'w':
                    $res *= 7;
                case 'd':
                    $res *= 24;
                case 'h':
                    $res *= 60;
                case 'w':
                    $res *= 60;
                    break;
                default:
            }
        }

        return $res;
    }

    private function stringToBool($val)
    {
        switch (strtolower($val)) {
            case 'true':
            case 'yes':
            case 'on':
                return true;
            case 'false':
            case 'no':
            case 'off':
                return false;
        }
    }
}
