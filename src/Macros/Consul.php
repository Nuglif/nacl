<?php

namespace Adoy\Nacl\Macros;

use Adoy\Nacl\MacroInterface;

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
