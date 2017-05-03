<?php

namespace Nuglif\Nacl;

interface ParserAware
{
    /**
     * @param Parser $parser
     * @return void
     */
    public function setParser(Parser $parser);
}
