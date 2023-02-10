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

abstract class Node
{
    private ?Node $parent = null;

    public function setParent(Node $parent): void
    {
        $this->parent = $parent;
    }

    public function getParent(): ?Node
    {
        return $this->parent;
    }

    public function getRoot(): Node
    {
        return $this->parent ? $this->parent->getRoot() : $this;
    }

    abstract public function getNativeValue(): mixed;
}
