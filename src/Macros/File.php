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
        $file = $this->parser->resolvePath($parameter);
        if (!$file) {
            $this->parser->error("Unable to read file '${parameter}'");
        }

        return file_get_contents($file);
    }

    public function setParser(Parser $parser)
    {
        $this->parser = $parser;
    }
}
