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
    public const ADD          = '+';
    public const SUB          = '-';
    public const OR_OPERATOR  = '|';
    public const AND_OPERATOR = '&';
    public const SHIFT_LEFT   = '<<';
    public const SHIFT_RIGHT  = '>>';
    public const MOD          = '%';
    public const DIV          = '/';
    public const MUL          = '*';
    public const POW          = '**';
    public const CONCAT       = '.';

    private mixed $left;
    private mixed $right;
    private mixed $operand;

    public function __construct(mixed $left, mixed $right, string $operand)
    {
        $this->left    = $left;
        $this->right   = $right;
        $this->operand = $operand;
    }

    public function setParent(Node $parent): void
    {
        if ($this->left instanceof Node) {
            $this->left->setParent($parent);
        }
        if ($this->right instanceof Node) {
            $this->right->setParent($parent);
        }
    }

    /**
     * @psalm-suppress UnhandledMatchCondition
     */
    public function getNativeValue(): mixed
    {
        $left  = $this->left instanceof Node ? $this->left->getNativeValue() : $this->left;
        $right = $this->right instanceof Node ? $this->right->getNativeValue() : $this->right;

        return match ($this->operand) {
            self::ADD => $left + $right,
            self::OR_OPERATOR => $left | $right,
            self::AND_OPERATOR => $left & $right,
            self::SHIFT_LEFT => $left << $right,
            self::SHIFT_RIGHT => $left >> $right,
            self::SUB => $left - $right,
            self::MUL => $left * $right,
            self::DIV => $left / $right,
            self::MOD => $left % $right,
            self::POW => $left ** $right,
            self::CONCAT => $left . $right,
        };
    }
}
