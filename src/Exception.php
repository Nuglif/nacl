<?php

namespace Nuglif\Nacl;

class Exception extends \Exception
{
    public function setContext($file, $line)
    {
        $this->file = $file;
        $this->line = $line;
    }
}
