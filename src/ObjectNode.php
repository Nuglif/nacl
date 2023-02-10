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
    private array $value    = [];
    private bool $isNative = true;

    public function __construct(array $values = [])
    {
        foreach ($values as $k => $v) {
            $this[$k] = $v;
        }
    }

    public function count(): int
    {
        return count($this->value);
    }

    public function merge(ObjectNode $a2): ObjectNode
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

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->value);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($value instanceof Node) {
            $value->setParent($this);
            $this->isNative = false;
        }
        $this->value[$offset] = $value;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->value[$offset];
    }

    public function has(mixed $offset): bool
    {
        return array_key_exists($offset, $this->value);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->value[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->value[$offset]);
    }

    public function getNativeValue(): array
    {
        if (!$this->isNative) {
            $this->resolve();
        }

        return $this->value;
    }

    private function resolve(): void
    {
        foreach ($this->value as $k => $v) {
            $this->value[$k] = $v instanceof Node ? $v->getNativeValue() : $v;
        }
        $this->isNative = true;
    }
}
