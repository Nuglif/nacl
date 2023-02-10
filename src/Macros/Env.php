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

namespace Nuglif\Nacl\Macros;

use Nuglif\Nacl\MacroInterface;
use Nuglif\Nacl\TypeCaster;

class Env implements MacroInterface
{
    public function getName(): string
    {
        return 'env';
    }

    public function execute(mixed $parameter, array $options = []): mixed
    {
        $options = array_merge([
            'type' => 'string',
        ], $options);

        $val = getenv($parameter);

        if (false === $val) {
            return array_key_exists('default', $options) ? $options['default'] : false;
        }

        return $this->cast($val, $options['type']);
    }

    private function cast(string $value, string $type): bool|string|int|float
    {
        return match ($type) {
            'bool', 'boolean' => TypeCaster::toBool($value),
            'int', 'integer' => (int) TypeCaster::toNum($value),
            'num', 'numeric' => TypeCaster::toNum($value),
            'str', 'string' => $value,
            default => throw new \InvalidArgumentException('Unknown type: ' . $type),
        };
    }
}
