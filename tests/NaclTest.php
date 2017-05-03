<?php

namespace Nuglif\Nacl;

define('TEST_CONST', 'value');
putenv('TEST=valid');

class NaclTest extends \PHPUnit\Framework\TestCase
{
    private $parser;

    public static function getJsonFiles()
    {
        $files = glob(__DIR__ . '/json/*.json');

        return array_map(function ($f) {
            return [$f];
        }, $files);
    }

    /**
     * @dataProvider getJsonFiles
     * @test
     */
    public function NaclIsJsonCompatible($jsonFile)
    {
        $this->assertSame(
            json_decode(file_get_contents($jsonFile), true),
            Nacl::parseFile($jsonFile)
        );
    }

    public static function getNaclFiles()
    {
        $files = glob(__DIR__ . '/nacl/*.conf');

        return array_map(function ($f) {
            @unlink($f . '.out');

            return [$f, str_replace('.conf', '.json', $f) ];
        }, $files);
    }

    /**
     * @dataProvider getNaclFiles
     * @test
     */
    public function testNacl($naclFile, $jsonFile)
    {
        $this->parser = Nacl::createParser();

        $this->parser->registerMacro(new Macros\Callback('vault', function ($p, $a = []) {
            return 'Fetch from vault=' . $p . ' or default' . $a['default'];
        }));

        $this->parser->registerMacro(new Macros\Callback('consul', function ($p, $a = []) {
            return 'Fetch from consul' . $p;
        }));

        $this->parser->registerMacro(new Macros\Callback('json_encode', function ($p) {
            return json_encode($p);
        }));

        $this->parser->setVariable('BAR', 'bar');
        $this->parser->setVariable('PREFIX', 'prefix');
        $result = $this->parser->parseFile($naclFile);
        try {
            $this->assertEquals(
                file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [],
                $result
            );
        } catch (\Exception $e) {
            file_put_contents($naclFile . '.out', json_encode($result, JSON_PRETTY_PRINT));
            throw $e;
        }
    }

    /**
     * @test
     * @expectedException Nuglif\Nacl\LexingException
     * @expectedExceptionMessage Unterminated string
     */
    public function testUnterminatedString()
    {
        Nacl::parse('foo="bar');
    }

    /**
     * @test
     * @expectedException Nuglif\Nacl\LexingException
     * @expectedExceptionMessage Unterminated HEREDOC
     */
    public function testUnterminatedHeredoc()
    {
        Nacl::parse("foo=<<<TEST\n");
    }

    /**
     * @test
     * @expectedException Nuglif\Nacl\LexingException
     * @expectedExceptionMessage Unterminated multiline comment
     */
    public function testUnterminatedMultilineComment()
    {
        Nacl::parse('/*');
    }

    /**
     * @test
     * @expectedException Nuglif\Nacl\ParsingException
     * @expectedExceptionMessage Syntax error, unexpected '10' (T_NUM)
     */
    public function testParseErrorMessageWithTokenName()
    {
        Nacl::parse('foo bar baz {}10;', 'file');
    }

    /**
     * @test
     * @expectedException Nuglif\Nacl\ParsingException
     * @expectedExceptionMessage Syntax error, unexpected ';'
     */
    public function testParseErrorMesageWithoutTokenName()
    {
        Nacl::parse('foo;', 'file');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function parsingUnexistingFileThrowInvalidArgumentException()
    {
        Nacl::parseFile('error');
    }
}
