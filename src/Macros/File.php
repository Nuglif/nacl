<?php

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
