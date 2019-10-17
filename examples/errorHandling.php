<?php

require __DIR__ . '/../vendor/autoload.php';

$parser = Nuglif\Nacl\Nacl::createParser();

$nacl = <<<NACL
{ "foo": "bar"
NACL;

try {
    var_dump($parser->parse($nacl));
} catch (Nuglif\Nacl\Exception $e) {
    die(sprintf("%s in %s on line %d\n", $e->getMessage(), $e->getFile(), $e->getLine()));
}
