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
    const REGEX_NAME        = '[A-Za-z_][A-Za-z0-9_]*';
    const REGEX_VAR         = '?:\${([A-Za-z0-9_]+)}';
    const REGEX_NUM         = '(?:[0-9]*\.?[0-9]+|[0-9]+\.)(?:[eE](?:\+|-)?[0-9]+)?(?:m(?:in|s)|[KkGgMm][Bb]?|[s|h|d|w|y])?';
    const REGEX_DQUOTE      = '"';
    const REGEX_HEREDOC     = '?:<<<([A-Za-z0-9_]+)\n';
    const REGEX_BOOL        = '(?:true|false|yes|no|on|off)\b';
    const REGEX_NULL        = 'null\b';
    const REGEX_TOKEN       = '[\[\]=:{};,.()&|%^/*+-]|<<|>>';
    const REGEX_ANY         = '.';

    private $textBuffer;

    public function __construct()
    {
        parent::__construct();
    }

    protected function getRules()
    {
        return [
            self::STATE_INITITAL => [
                self::REGEX_SPACE      => false,
                self::REGEX_COMMENT    => false,
                self::REGEX_COMMENT_ML => function ($yylval) {
                    $pos = strpos($this->content, '*/', $this->count);
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
                    $yylval = TypeCaster::toBool($yylval);

                    return Token::T_BOOL;
                },
                self::REGEX_NULL    => function (&$yylval) {
                    $yylval = null;

                    return Token::T_NULL;
                },
                self::REGEX_NUM     => function (&$yylval) {
                    $yylval = TypeCaster::toNum($yylval);

                    return Token::T_NUM;
                },
                self::REGEX_NAME    => function () {
                    return Token::T_NAME;
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
                self::REGEX_TOKEN   => function ($yylval) {
                    return $yylval;
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
                            $utfCode = substr($this->content, $this->count, 4);
                            if (preg_match('/[A-Fa-f0-9]{4,4}/', $utfCode)) {
                                $utf = hexdec($utfCode);
                                $this->count += 4;
                                // UTF-32 ?
                                if ($utf >= 0xD800 && $utf <= 0xDBFF && preg_match('/^\\\\u[dD][c-fC-F][0-9a-fA-F][0-9a-fA-F]/', substr($this->content, $this->count, 6), $matches)) {
                                    $utf_hi = hexdec(substr($matches[0], -4));
                                    $utf = (($utf & 0x3FF) << 10) + ($utf_hi & 0x3FF) + 0x10000;
                                    $this->count += 6;
                                }
                                $this->textBuffer .= $this->fromCharCode($utf);
                                break;
                            }
                            /* No break */
                        default:
                            $this->textBuffer .= '\\' . $yylval;
                            break;
                    }
                },
                '\$' => function (&$yylval) {
                    if (preg_match('/^{([A-Za-z0-9_]+)}/', substr($this->content, $this->count), $matches)) {
                        $this->count += strlen($matches[0]);
                        $yylval = $matches[1];

                        return Token::T_ENCAPSED_VAR;
                    }

                    $this->textBuffer .= $yylval;
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

    private function fromCharCode($bytes)
    {
        switch (true) {
            case ((0x7F & $bytes) == $bytes):
                return chr($bytes);

            case (0x07FF & $bytes) == $bytes:
                return chr(0xc0 | ($bytes >> 6))
                     . chr(0x80 | ($bytes & 0x3F));

            case (0xFFFF & $bytes) == $bytes:
                return chr(0xe0 | ($bytes >> 12))
                     . chr(0x80 | (($bytes >> 6) & 0x3F))
                     . chr(0x80 | ($bytes & 0x3F));

            default:
                return chr(0xF0 | ($bytes >> 18))
                     . chr(0x80 | (($bytes >> 12) & 0x3F))
                     . chr(0x80 | (($bytes >> 6) & 0x3F))
                     . chr(0x80 | ($bytes & 0x3F));
        }
    }
}
