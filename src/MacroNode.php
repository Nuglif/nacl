<?php

namespace Nuglif\Nacl;

class MacroNode extends Node
{
    private $callback;
    private $param;
    private $options;

    public function __construct(callable $callback, $param, ObjectNode $options)
    {
        $this->callback = $callback;
        $this->param = $param;
        $this->options = $options;
    }

    public function execute()
    {
        $result = $this->getNativeValue();

        if (is_array($result)) {
            $result = is_int(key($result)) ? new ArrayNode(array_values($result)) : new ObjectNode($result);
        }

        return $result;
    }

    public function getNativeValue()
    {
        $callback = $this->callback;

        return $callback(
            $this->param instanceof Node ? $this->param->getNativeValue() : $this->param,
            $this->options->getNativeValue()
        );
    }
}
