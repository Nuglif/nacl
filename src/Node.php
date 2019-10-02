<?php

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
