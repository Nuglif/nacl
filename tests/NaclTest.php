<?php

namespace Nuglif\Nacl;

use Nuglif\Nacl\LexingException;
use Nuglif\Nacl\ParsingException;
use Nuglif\Nacl\ReferenceException;

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

        $this->parser->registerMacro(new Macros\Callback('testMacroWithOptions', function ($p, $a = []) {
            return [
                'param' => $p,
                'options' => $a
            ];
        }));

        $this->parser->registerMacro(new Macros\Callback('testMacro', function ($p, $a = []) {
            return $p;
        }));

        $this->parser->registerMacro(new Macros\Callback('json_encode', function ($p) {
            return json_encode($p);
        }));

        $this->parser->setVariable('BAR', 'bar');
        $this->parser->setVariable('MY_VAR', 'my var value');
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
     */
    public function testUnterminatedString()
    {
        $this->expectException(LexingException::class);
        $this->expectExceptionMessage('Unterminated string');

        Nacl::parse('foo="bar');
    }

    /**
     * @test
     */
    public function testUnterminatedHeredoc()
    {
        $this->expectException(LexingException::class);
        $this->expectExceptionMessage('Unterminated HEREDOC');

        Nacl::parse("foo=<<<TEST\n");
    }

    /**
     * @test
     */
    public function testUnterminatedMultilineComment()
    {
        $this->expectException(LexingException::class);
        $this->expectExceptionMessage('Unterminated multiline comment');

        Nacl::parse('/*');
    }

    /**
     * @test
     */
    public function testParseErrorMessageWithTokenName()
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Syntax error, unexpected \'10\' (T_NUM)');

        Nacl::parse('foo bar baz {}10;', 'file');
    }

    /**
     * @test
     */
    public function testParseErrorMesageWithoutTokenName()
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Syntax error, unexpected \';\'');

        Nacl::parse('foo;', 'file');
    }

    /**
     * @test
     */
    public function parsingUnexistingFileThrowInvalidArgumentException()
    {
        $this->expectException(\InvalidArgumentException::class);

        Nacl::parseFile('error');
    }

    /**
     * @test
     */
    public function testMacroInMoreGlobalScope()
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Macro without assignation key must return an object');

        Nacl::parse('.env unexistingenv');
    }

    /**
     * @test
     */
    public function testRegisterMacro()
    {
        Nacl::registerMacro($macro = new Macros\Callback('strtoupper', function ($p) {
            return strtoupper($p);
        }));
        $this->assertSame(['foo' => 'BAR'], Nacl::parse('foo .strtoupper bar'));
        $this->assertSame(['foo' => 'BAR'], Nacl::parse('${BAR} = .strtoupper bar; foo ${BAR};'));
    }

    /**
     * @test
     */
    public function testLazyMacroExecution()
    {
        $this->parser = Nacl::createParser();
        $this->parser->registerMacro($macro = new Macros\Callback('error', function ($p) {
            throw new \Exception($p);
        }));

        $this->assertSame(['foo' => 'bar'], $this->parser->parse('foo .error "FAIL"; foo bar;'));
    }

    /**
     * @test
     */
    public function refWithoutString()
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessage('.ref expects parameter to be string, array given.');

        Nacl::parse('ref .ref {}');
    }

    /**
     * @test
     */
    public function circularDependencyDetection()
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessage('Circular dependence detected.');

        Nacl::parse('foo .ref "./bar"; bar .ref "foo"');
    }

    /**
     * @test
     */
    public function undefinedRef()
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessage('Undefined property: bar.');

        Nacl::parse('foo .ref "bar";');
    }
}
