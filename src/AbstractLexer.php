<?php

namespace Adoy\Nacl;

abstract class AbstractLexer
{
    const STATE_INITITAL = 0;
    const EOF            = '<<EOF>>';

    private $regexes   = [];
    private $tokenMaps = [];

    private $state   = self::STATE_INITITAL;
    private $stack   = [];

    protected $line;
    protected $content;
    protected $count;
    protected $filename;

    abstract protected function getRules();

    protected $terminate = false;

    public function __construct()
    {
        foreach ($this->getRules() as $state => $patterns) {
            $eofCallback = [ $this, 'yyterminate' ];

            if (isset($patterns[self::EOF])) {
                $eofCallback = $patterns[self::EOF];
                unset($patterns[self::EOF]);
            }

            $this->regexes[$state]   = $this->computeRegex(array_keys($patterns));
            $this->tokenMaps[$state] = array_values($patterns);

            $this->tokenMaps[$state][-1] = $eofCallback;
        }
    }

    protected function yyterminate()
    {
        $this->terminate = true;
    }

    public function yylex()
    {
        while (!$this->terminate) {
            if (isset($this->content[$this->count])) {
                if (!preg_match($this->regexes[$this->state], $this->content, $matches, null, $this->count)) {
                    $this->error(sprintf('Unexpected character "%s"', $this->content[$this->count]));
                }

                for ($i = 1; '' === $matches[$i]; ++$i);
                $this->count += strlen($matches[0]);
                $this->line += substr_count($matches[0], "\n");
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
        }
    }

    protected function error($errorMessage)
    {
        throw new LexingException($errorMessage, $this->filename, $this->line);
    }

    protected function begin($state)
    {
        $this->state = $state;
    }

    private function computeRegex($patterns)
    {
        return '#\G(' . implode(')|\G(', $patterns) . ')#Ai';
    }

    public function push($content, $filename = null)
    {
        $this->terminate = false;

        if (null !== $this->content) {
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

    public function pop()
    {
        if (empty($this->stack)) {
            return false;
        }

        list(
            $this->line,
            $this->content,
            $this->count,
            $this->filename
        ) = array_pop($this->stack);

        return true;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function getFilename()
    {
        return $this->filename;
    }
}
