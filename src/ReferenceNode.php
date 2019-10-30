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

class ReferenceNode extends Node
{
    const ROOT = '/';

    private $path;
    private $isResolving = false;
    private $isResolved  = false;
    private $file;
    private $line;
    private $value;

    public function __construct($path, $file, $line)
    {
        $this->path = $path;
        $this->file = $file;
        $this->line = $line;
    }

    public function getNativeValue()
    {
        if (!$this->isResolved) {
            $this->resolve();
        }

        return $this->value;
    }

    private function resolve()
    {
        if ($this->isResolving) {
            throw new ReferenceException('Circular dependence detected.', $this->file, $this->line);
        }
        if ($this->path instanceof Node) {
            $this->path = $this->path->getNativeValue();
        }
        if (!is_string($this->path)) {
            throw new ReferenceException(sprintf('.ref expects parameter to be string, %s given.', gettype($this->path)), $this->file, $this->line);
        }

        $this->isResolving = true;
        $value             = $this->isAbsolute() ? $this->getRoot() : $this->getParent();

        foreach (explode('/', ltrim($this->path, self::ROOT)) as $path) {
            switch ($path) {
                case '.':
                case '':
                    break;
                case '..':
                    $value = $value->getParent();
                    break;
                default:
                    if (!$value instanceof ObjectNode || !isset($value[$path])) {
                        throw new ReferenceException(sprintf('Undefined property: %s.', $this->path), $this->file, $this->line);
                    }
                    $value = $value[$path];
            }
        }

        if ($value instanceof Node) {
            $value = $value->getNativeValue();
        }

        $this->isResolving = false;
        $this->isResolved  = true;
        $this->value       = $value;
    }

    private function isAbsolute()
    {
        return self::ROOT === substr($this->path, 0, 1);
    }
}
