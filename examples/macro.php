<?php

require __DIR__ . '/../vendor/autoload.php';

class Base64DecodeMacro implements \Nuglif\Nacl\MacroInterface
{
    public function getName()
    {
        return 'base64_decode';
    }

    public function execute($param, array $options = [])
    {
        return base64_decode($param);
    }
}

$parser = Nuglif\Nacl\Nacl::createParser();
$parser->registerMacro(new Base64DecodeMacro);
var_dump($parser->parseFile('macro.conf'));
