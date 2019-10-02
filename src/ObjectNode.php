<?php

namespace Nuglif\Nacl;

class ObjectNode implements \IteratorAggregate, \ArrayAccess, \Countable, Node
{
    private $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
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
