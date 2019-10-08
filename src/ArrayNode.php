<?php

namespace Nuglif\Nacl;

class ArrayNode extends Node implements \IteratorAggregate, \Countable
{
    private $value;
    private $isNative = true;

    public function __construct(array $defaultValues = [])
    {
        $this->value = $defaultValues;
    }

    public function add($item)
    {
        if ($item instanceof Node) {
            $item->setParent($this);
            $this->isNative = false;
        }

        $this->value[] = $item;
    }

    public function count()
    {
        return count($this->value);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->value);
    }

    public function getNativeValue()
    {
        if (!$this->isNative) {
            $this->resolve();
        }

        return $this->value;
    }

    private function resolve()
    {
        foreach ($this->value as $k => $v) {
            $this->value[$k] = $v instanceof Node ? $v->getNativeValue() : $v;
        }
        $this->isNative = true;
    }
}
