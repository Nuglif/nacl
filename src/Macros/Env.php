<?php

namespace Adoy\Nacl\Macros;

use Adoy\Nacl\MacroInterface;

class Env implements MacroInterface
{
    public function getName()
    {
        return 'env';
    }

    public function execute($parameter, array $options = [])
    {
        return getenv($parameter);
    }
}
