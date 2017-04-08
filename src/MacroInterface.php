<?php

namespace Nuglif\Nacl;

interface MacroInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @param mixed $parameter;
     * @param array $options
     * @return mixed
     */
    public function execute($parameter, array $options = []);
}
