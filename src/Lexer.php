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

class Lexer extends AbstractLexer
{
    protected const STATE_INSTRING  = 1;
    protected const STATE_INHEREDOC = 2;

    public const REGEX_SPACE      = '[ \t\n\r]+';
    public const REGEX_COMMENT    = '(?://|\#).*';
    public const REGEX_COMMENT_ML = '/\*';
    public const REGEX_NAME       = '[A-Za-z_][A-Za-z0-9_-]*';
    public const REGEX_VAR        = '?:\${([A-Za-z0-9_]+)}';
    public const REGEX_NUM        = '(?:[0-9]*\.?[0-9]+|[0-9]+\.)(?:[eE](?:\+|-)?[0-9]+)?(?:m(?:in|s)|[KkGgMm][Bb]?|[b|s|h|d|w|y])?';
    public const REGEX_DQUOTE     = '"';
    public const REGEX_HEREDOC    = '?:<<<([A-Za-z0-9_]+)\n';
    public const REGEX_BOOL       = '(?:true|false|yes|no|on|off)\b';
    public const REGEX_NULL       = 'null\b';
    public const REGEX_TOKEN      = '[\[\]=:{};,.()&|%^/*+-]|<<|>>';
    public const REGEX_ANY        = '.';

    private string $textBuffer = '';

    protected function getRules(): array
    {
        return [
            self::STATE_INITIAL => [
                self::REGEX_SPACE      => false,
                self::REGEX_COMMENT    => false,
                self::REGEX_COMMENT_ML => function () {
                    $pos = strpos($this->content, '*/', $this->count);
                    if (false === $pos) {
                        $this->line += substr_count(substr($this->content, $this->count), "\n");
                        $this->error('Unterminated multiline comment');
                    }
                    $this->line += substr_count(substr($this->content, $this->count, (int) $pos - $this->count + 2), "\n");
                    $this->count = (int) $pos + 2;
                },
                self::REGEX_DQUOTE => function (): void {
                    $this->begin(self::STATE_INSTRING);
                    $this->textBuffer = '';
                },
                self::REGEX_BOOL => function (mixed &$yylval): int {
                    $yylval = TypeCaster::toBool($yylval);

                    return Token::T_BOOL;
                },
                self::REGEX_NULL => function (mixed &$yylval): int {
                    $yylval = null;

                    return Token::T_NULL;
                },
                self::REGEX_NUM => function (mixed &$yylval): int {
                    $yylval = TypeCaster::toNum($yylval);

                    return Token::T_NUM;
                },
                self::REGEX_NAME => fn() => Token::T_NAME,
                self::REGEX_HEREDOC => function (mixed &$yylval): int {
                    $needle = "\n" . $yylval;
                    $pos = strpos($this->content, $needle, $this->count);
                    if (false === $pos) {
                        $this->line += substr_count(substr($this->content, $this->count), "\n");
                        $this->error('Unterminated HEREDOC');
                    }

                    $yylval = substr($this->content, $this->count, (int) $pos - $this->count);
                    $this->line += substr_count($yylval, "\n") + 1;
                    $this->count += strlen($yylval) + strlen($needle);

                    return Token::T_END_STR;
                },
                self::REGEX_TOKEN => fn(mixed $yylval): string => $yylval,
                self::REGEX_VAR => fn(): int => Token::T_VAR,
                self::REGEX_ANY => function (mixed $yylval): void {
                    $this->error('Unexpected char \'' . $yylval . '\'');
                },
                self::EOF => fn(): int => Token::T_EOF,
            ],
            self::STATE_INSTRING => [
                '[^\\\"$]+' => function (mixed &$yylval) {
                    $this->textBuffer .= $yylval;
                    if ('$' == substr($this->content, $this->count, 1)) {
                        $yylval = $this->textBuffer;
                        $this->textBuffer = '';

                        return Token::T_STRING;
                    }
                },
                '?:\\\(.)' => function (mixed $yylval) {
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
                            /* no break */
                        default:
                            $this->textBuffer .= '\\' . $yylval;
                            break;
                    }
                },
                '\$' => function (mixed &$yylval) {
                    if (preg_match('/^{([A-Za-z0-9_]+)}/', substr($this->content, $this->count), $matches)) {
                        $this->count += strlen($matches[0]);
                        $yylval = $matches[1];

                        return Token::T_ENCAPSED_VAR;
                    }

                    $this->textBuffer .= $yylval;
                },
                self::REGEX_DQUOTE => function (mixed &$yylval) {
                    $yylval = $this->textBuffer;
                    $this->begin(self::STATE_INITIAL);

                    return Token::T_END_STR;
                },
                self::EOF => function () {
                    $this->error('Unterminated string');
                },
            ],
        ];
    }

    private function fromCharCode(int $bytes): string
    {
        return match (true) {
            (0x7F & $bytes) == $bytes => chr($bytes),
            (0x07FF & $bytes) == $bytes => chr(0xc0 | ($bytes >> 6))
                 . chr(0x80 | ($bytes & 0x3F)),
            (0xFFFF & $bytes) == $bytes => chr(0xe0 | ($bytes >> 12))
                 . chr(0x80 | (($bytes >> 6) & 0x3F))
                 . chr(0x80 | ($bytes & 0x3F)),
            default => chr(0xF0 | ($bytes >> 18))
                 . chr(0x80 | (($bytes >> 12) & 0x3F))
                 . chr(0x80 | (($bytes >> 6) & 0x3F))
                 . chr(0x80 | ($bytes & 0x3F)),
        };
    }
}
