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

class OperationNode extends Node
{
    const ADD          = '+';
    const SUB          = '-';
    const OR_OPERATOR  = '|';
    const AND_OPERATOR = '&';
    const SHIFT_LEFT   = '<<';
    const SHIFT_RIGHT  = '>>';
    const MOD          = '%';
    const DIV          = '/';
    const MUL          = '*';
    const POW          = '**';
    const CONCAT       = '.';

    private $left;
    private $right;
    private $operand;

    public function __construct($left, $right, $operand)
    {
        $this->left    = $left;
        $this->right   = $right;
        $this->operand = $operand;
    }

    public function setParent(Node $parent)
    {
        if ($this->left instanceof Node) {
            $this->left->setParent($parent);
        }
        if ($this->right instanceof Node) {
            $this->right->setParent($parent);
        }
    }

    public function getNativeValue()
    {
        $left  = $this->left instanceof Node ? $this->left->getNativeValue() : $this->left;
        $right = $this->right instanceof Node ? $this->right->getNativeValue() : $this->right;

        switch ($this->operand) {
            case self::ADD:
                return $left + $right;
            case self::OR_OPERATOR:
                return $left | $right;
            case self::AND_OPERATOR:
                return $left & $right;
            case self::SHIFT_LEFT:
                return $left << $right;
            case self::SHIFT_RIGHT:
                return $left >> $right;
            case self::SUB:
                return $left - $right;
            case self::MUL:
                return $left * $right;
            case self::DIV:
                return $left / $right;
            case self::MOD:
                return $left % $right;
            case self::POW:
                return $left ** $right;
            case self::CONCAT:
                return $left . $right;
        }
    }
}
