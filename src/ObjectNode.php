<?php

namespace Nuglif\Nacl;

class ObjectNode extends Node implements  \IteratorAggregate, \ArrayAccess, \Countable
{
    private $value = [];
    private $isNative = true;

    public function __construct(array $values = [])
    {
        foreach ($values as $k => $v) {
            $this[$k] = $v;
        }
    }

    public function count()
    {
        return count($this->value);
    }

    public function merge(ObjectNode $a2)
    {
        if (0 === count($this)) {
            return $a2;
        } elseif (0 === count($a2)) {
            return $this;
        }

        foreach ($a2 as $key => $value) {
            if (!isset($this[$key]) || !($this[$key] instanceof ObjectNode) || !($value instanceof ObjectNode)) {
                $this[$key] = $value;
            } else {
                $this[$key] = $this[$key]->merge($value);
            }
        }

        return $this;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->value);
    }

    public function offsetSet($offset, $value)
    {
        if ($value instanceof Node) {
            $value->setParent($this);
            $this->isNative = false;
        }
        $this->value[$offset] = $value;
    }

    public function offsetGet($offset)
    {
        return $this->value[$offset];
    }

    public function offsetExists($offset)
    {
        return isset($this->value[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->value[$offset]);
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
