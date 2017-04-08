<?php

namespace Nuglif\Nacl\Macros;

use Nuglif\Nacl\MacroInterface;

class Callback implements MacroInterface
{
    private $name;
    private $callback;

    public function __construct($name, callable $callback)
    {
        $this->name     = $name;
        $this->callback = $callback;
    }

    public function getName()
    {
        return $this->name;
    }

    public function execute($parameter, array $options = [])
    {
        $callback = $this->callback;

        return $callback($parameter, $options);
    }
}
