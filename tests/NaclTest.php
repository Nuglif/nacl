<?php

namespace Nuglif\Nacl;

define('TEST_CONST', 'value');
putenv('TEST=valid');

class NaclTest extends \PHPUnit\Framework\TestCase
{
    private $parser;

    public function setUp()
    {
        $lexer        = new Lexer();
        $this->parser = new Parser($lexer);

        $this->parser->registerMacro(new Macros\Callback('vault', function ($p, $a = []) {
            return 'Fetch from vault=' . $p . ' or default' . $a['default'];
        }));

        $this->parser->registerMacro(new Macros\Callback('consul', function ($p, $a = []) {
            return 'Fetch from consul' . $p;
        }));

        $this->parser->registerMacro(new Macros\Callback('json_encode', function ($p) {
            return json_encode($p);
        }));

        $this->parser->registerMacro(new Macros\Env);
        $this->parser->registerMacro(new Macros\Constant);

        $this->parser->setVariable('BAR', 'bar');
        $this->parser->setVariable('PREFIX', 'prefix');
    }

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
            $this->parser->parseFile($jsonFile)
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
        $this->parser->parse('foo="bar');
    }

    /**
     * @test
     * @expectedException Nuglif\Nacl\LexingException
     * @expectedExceptionMessage Unterminated HEREDOC
     */
    public function testUnterminatedHeredoc()
    {
        $this->parser->parse("foo=<<<TEST\n");
    }

    /**
     * @test
     * @expectedException Nuglif\Nacl\LexingException
     * @expectedExceptionMessage Unterminated multiline comment
     */
    public function testUnterminatedMultilineComment()
    {
        $this->parser->parse('/*');
    }

    /**
     * @test
     * @expectedException Nuglif\Nacl\ParsingException
     * @expectedExceptionMessage Syntax error, unexpected 'baz' (T_NAME)
     */
    public function testParseErrorMessageWithTokenName()
    {
        $this->parser->parse('foo bar baz;', 'file');
    }

    /**
     * @test
     * @expectedException Nuglif\Nacl\ParsingException
     * @expectedExceptionMessage Syntax error, unexpected ';'
     */
    public function testParseErrorMesageWithoutTokenName()
    {
        $this->parser->parse('foo;', 'file');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function parsingUnexistingFileThrowInvalidArgumentException()
    {
        $this->parser->parseFile('error');
    }
}
