<?php

namespace Nuglif\Nacl\Macros;

use Nuglif\Nacl\MacroInterface;

class Constant implements MacroInterface
{
    public function getName()
    {
        return 'const';
    }

    public function execute($parameter, array $options = [])
    {
        if (!is_string($parameter)) {
            throw new \InvalidArgumentException('Constant parameter must be a string');
        }

        if (!defined($parameter) && isset($options['default'])) {
            return $options['default'];
        }

        return constant($parameter);
    }
}
