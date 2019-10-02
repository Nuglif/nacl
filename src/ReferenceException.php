<?php

namespace Nuglif\Nacl;

class ReferenceException extends Exception
{
    public function __construct($message, $file, $line)
    {
        parent::__construct($message);
        $this->setContext($file, $line);
    }
}
