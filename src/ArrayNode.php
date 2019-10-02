<?php

namespace Nuglif\Nacl;

class ArrayNode extends Node implements \IteratorAggregate, \Countable
{
    private $array;

    public function __construct(array $defaultValues = [])
    {
        $this->array = $defaultValues;
    }

    public function add($item)
    {
        if ($item instanceof Node) {
            $item->setParent($this);
        }

        $this->array[] = $item;
    }

    public function count()
    {
        return count($this->array);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->array);
    }

    public function getNativeValue()
    {
        $result = [];
        foreach ($this->array as $k => $v) {
            $result[$k] = $v instanceof Node ? $v->getNativeValue() : $v;
        }

        return $result;
    }
}
