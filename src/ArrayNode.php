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

/**
 * @template-implements \IteratorAggregate<mixed>
 */
class ArrayNode extends Node implements \IteratorAggregate, \Countable
{
    private array $value = [];
    private bool $isNative = true;

    public function __construct(array $defaultValues = [])
    {
        foreach ($defaultValues as $v) {
            $this->add($v);
        }
    }

    public function add(mixed $item): void
    {
        if ($item instanceof Node) {
            $item->setParent($this);
            $this->isNative = false;
        }

        $this->value[] = $item;
    }

    public function count(): int
    {
        return count($this->value);
    }

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->value);
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
