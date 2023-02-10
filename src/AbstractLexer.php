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

abstract class AbstractLexer
{
    protected const EOF           = -1;
    protected const STATE_INITIAL = 0;

    private array $regexes   = [];
    private array $tokenMaps = [];

    private int $state = self::STATE_INITIAL;
    private array $stack = [];

    protected int $line = 0;
    protected string $content = '';
    protected int $count = 0;
    protected string $filename = '';

    abstract protected function getRules(): array;

    public function __construct()
    {
        foreach ($this->getRules() as $state => $patterns) {
            $eofCallback = false;

            if (isset($patterns[self::EOF])) {
                $eofCallback = $patterns[self::EOF];
                unset($patterns[self::EOF]);
            }

            $this->regexes[$state]   = $this->computeRegex(array_keys($patterns));
            $this->tokenMaps[$state] = array_values($patterns);

            $this->tokenMaps[$state][-1] = $eofCallback;
        }
    }

    /**
     * @psalm-suppress InvalidReturnType
     */
    public function yylex(): Token
    {
        do {
            if (isset($this->content[$this->count])) {
                if (!preg_match($this->regexes[$this->state], $this->content, $matches, 0, $this->count)) {
                    $this->error(sprintf('Unexpected character "%s"', $this->content[$this->count]));
                }
                for ($i = 1; '' === $matches[$i]; ++$i) {
                }
                $this->count += strlen($matches[0]);
                $this->line  += substr_count($matches[0], "\n");
            } else {
                $i       = 0;
                $matches = [ '' ];
            }

            if ($this->tokenMaps[$this->state][$i - 1]) {
                $callback = $this->tokenMaps[$this->state][$i - 1];
                if ($token = $callback($matches[$i])) {
                    return new Token($token, $matches[$i]);
                }
            }
        } while ($i);
    }

    protected function error(string $errorMessage): void
    {
        throw new LexingException($errorMessage, $this->filename, $this->line);
    }

    protected function begin(int $state): void
    {
        $this->state = $state;
    }

    private function computeRegex(array $patterns): string
    {
        return '#\G(' . implode(')|\G(', $patterns) . ')#A';
    }

    public function push(string $content, string $filename): void
    {
        if ('' !== $this->content) {
            $this->stack[] = [
                $this->line,
                $this->content,
                $this->count,
                $this->filename,
            ];
        }

        $this->line     = 1;
        $this->content  = $content;
        $this->count    = 0;
        $this->filename = $filename;
    }

    public function pop(): bool
    {
        if (empty($this->stack)) {
            return false;
        }

        [
            $this->line,
            $this->content,
            $this->count,
            $this->filename
        ] = array_pop($this->stack);

        return true;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }
}
