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

class ReferenceNode extends Node
{
    public const ROOT = '/';

    private mixed $path;
    private bool $isResolving = false;
    private bool $isResolved  = false;
    private string $file;
    private int $line;
    private mixed $value = null;
    private ObjectNode $options;

    public function __construct(string|Node $path, string $file, int $line, ObjectNode $options)
    {
        $this->path = $path;
        $this->file = $file;
        $this->line = $line;
        $this->options = $options;
    }

    public function getNativeValue(): mixed
    {
        if (!$this->isResolved) {
            $this->resolve();
        }

        return $this->value;
    }

    private function resolve(): void
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
                    $value = $value?->getParent();
                    break;
                default:
                    /** @psalm-suppress PossiblyUndefinedMethod */
                    if (!isset($value[$path])) {
                        if ($this->options->has('default')) {
                            $value = $this->options['default'];
                            break 2;
                        }
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

    private function isAbsolute(): bool
    {
        return self::ROOT === substr($this->path, 0, 1);
    }
}
