<?php
/**
 * This file is part of NACL.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright 2019 Nuglif (2018) Inc.
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author    Pierrick Charron <pierrick@adoy.net>
 * @author    Charle Demers <charle.demers@gmail.com>
 */

declare(strict_types=1);

namespace Nuglif\Nacl;

class ObjectNode extends Node implements \IteratorAggregate, \ArrayAccess, \Countable
{
    private $value    = [];
    private $isNative = true;

    public function __construct(array $values = [])
    {
        foreach ($values as $k => $v) {
            $this[$k] = $v;
        }
    }

    #[\ReturnTypeWillChange]
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

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->value);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ($value instanceof Node) {
            $value->setParent($this);
            $this->isNative = false;
        }
        $this->value[$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->value[$offset];
    }

    public function has($offset)
    {
        return array_key_exists($offset, $this->value);
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->value[$offset]);
    }

    #[\ReturnTypeWillChange]
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
