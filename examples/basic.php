<?php

require __DIR__ . '/../vendor/autoload.php';

$parser = Nuglif\Nacl\Nacl::createParser();
var_dump($parser->parseFile('basic.conf'));
