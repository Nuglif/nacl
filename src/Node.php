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

namespace Nuglif\Nacl;

abstract class Node
{
    private $parent;
    private $root;

    public function setParent(Node $parent)
    {
        $this->parent = $parent;
    }

    public function getParent()
    {
        return $this->parent ?: null;
    }

    public function getRoot()
    {
        return $this->parent ? $this->parent->getRoot() : $this;
    }

    abstract public function getNativeValue();
}
