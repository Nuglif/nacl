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

namespace Nuglif\Nacl\Macros;

use Nuglif\Nacl\MacroInterface;
use Nuglif\Nacl\Parser;
use Nuglif\Nacl\ParserAware;

class File implements MacroInterface, ParserAware
{
    private $parser;

    public function getName()
    {
        return 'file';
    }

    public function execute($parameter, array $options = [])
    {
        if ($file = $this->parser->resolvePath($parameter)) {
            return file_get_contents($file);
        }

        if (array_key_exists('default', $options)) {
            return $options['default'];
        }

        $this->parser->error("Unable to read file '${parameter}'");
    }

    public function setParser(Parser $parser)
    {
        $this->parser = $parser;
    }
}
