<?php

namespace Nuglif\Nacl;

class ObjectNode extends Node implements  \IteratorAggregate, \ArrayAccess, \Countable
{
    private $values = [];

    public function __construct(array $values = [])
    {
        foreach ($values as $k => $v) {
            $this[$k] = $v;
        }
    }

    public function count()
    {
        return count($this->values);
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
        return new \ArrayIterator($this->values);
    }

    public function offsetSet($offset, $value)
    {
        if ($value instanceof Node) {
            $value->setParent($this);
        }
        $this->values[$offset] = $value;
    }

    public function offsetGet($offset)
    {
        return $this->values[$offset];
    }

    public function offsetExists($offset)
    {
        return isset($this->values[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->values[$offset]);
    }

    public function getNativeValue()
    {
        $result = [];
        foreach ($this->values as $k => $v) {
            $result[$k] = $v instanceof Node ? $v->getNativeValue() : $v;
        }

        return $result;
    }
}
