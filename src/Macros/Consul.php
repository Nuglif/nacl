<?php

namespace Nuglif\Nacl\Macros;

use Nuglif\Nacl\MacroInterface;

class Consul implements MacroInterface
{
    public function getName()
    {
        return 'consul';
    }

    public function execute($parameter, array $options = [])
    {
    }
}
