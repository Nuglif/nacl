<?php

require __DIR__ . '/../vendor/autoload.php';

class Base64DecodeMacro implements \Nuglif\Nacl\MacroInterface
{
    public function getName(): string
    {
        return 'base64_decode';
    }

    public function execute(mixed $param, array $options = []): mixed
    {
        return base64_decode($param);
    }
}

$parser = Nuglif\Nacl\Nacl::createParser();
$parser->registerMacro(new Base64DecodeMacro);
var_dump($parser->parseFile('macro.conf'));
