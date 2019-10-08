<?php

namespace Nuglif\Nacl;

class MacroNode extends Node
{
    private $callback;
    private $param;
    private $options;
    private $value;
    private $isResolved = false;

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
        if (!$this->isResolved) {
            $this->resolve();
        }

        return $this->value;
    }

    private function resolve()
    {
        $callback = $this->callback;
        $this->value = $callback(
            $this->param instanceof Node ? $this->param->getNativeValue() : $this->param,
            $this->options->getNativeValue()
        );
        $this->isResolved = true;
    }
}
