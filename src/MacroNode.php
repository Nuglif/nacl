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

class MacroNode extends Node
{
    private $callback;
    private $param;
    private $options;
    private $value;
    private $isResolved = false;

    public function __construct(callable $callback, $param, ObjectNode $options)
    {
        $this->callback = $callback;
        $this->param    = $param;
        $this->options  = $options;
    }

    public function execute()
    {
        $result = $this->getNativeValue();

        if (is_array($result)) {
            $result = is_int(key($result)) ? new ArrayNode(array_values($result)) : new ObjectNode($result);
        }

        return $result;
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
        $callback    = $this->callback;
        $this->value = $callback(
            $this->param instanceof Node ? $this->param->getNativeValue() : $this->param,
            $this->options->getNativeValue()
        );
        $this->isResolved = true;
    }

    public function setParent(Node $parent)
    {
        if ($this->param instanceof Node) {
            $this->param->setParent($parent);
        }
    }
}
