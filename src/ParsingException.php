<?php

namespace Adoy\Nacl;

class ParsingException extends \Exception
{
    public function __construct($message, $file = null, $line = 0)
    {
        parent::__construct($message);
        if ($file) {
            $this->file = $file;
        }
        if ($line) {
            $this->line = $line;
        }
    }
}
