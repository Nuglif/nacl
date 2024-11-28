<?php

declare(strict_types=1);

namespace Nuglif\Nacl;

class NaclTest extends \PHPUnit\Framework\TestCase
{
    private Parser $parser;

    public static function setUpBeforeClass(): void
    {
        define('TEST_CONST', 'value');
        putenv('TEST=valid');
    }

    public static function getJsonFiles(): iterable
    {
        $files = glob(__DIR__ . '/json/*.json');

        return array_map(fn($f) => [$f], $files);
    }

    /**
     * @dataProvider getJsonFiles
     * @test
     */
    public function naclIsJsonCompatible(string $jsonFile): void
    {
        $this->assertSame(
            json_decode(file_get_contents($jsonFile), true, 512, JSON_THROW_ON_ERROR),
            Nacl::parseFile($jsonFile)
        );
    }

    public static function getNaclFiles(): iterable
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
    public function testNacl(string $naclFile, string $jsonFile): void
    {
        $this->parser = Nacl::createParser();

        $this->parser->registerMacro(new Macros\Callback('testMacroWithOptions', fn($p, $a = []) => [
            'param'   => $p,
            'options' => $a,
        ]));

        $this->parser->registerMacro(new Macros\Callback('testMacro', fn($p, $a = []) => $p));

        $this->parser->registerMacro(new Macros\Callback('json_encode', fn($p) => json_encode($p, JSON_THROW_ON_ERROR)));

        $this->parser->setVariable('BAR', 'bar');
        $this->parser->setVariable('MY_VAR', 'my var value');
        $result = $this->parser->parseFile($naclFile);
        try {
            $this->assertEquals(
                file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true, 512, JSON_THROW_ON_ERROR) : [],
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
    public function testUnterminatedString(): void
    {
        $this->expectException(LexingException::class);
        $this->expectExceptionMessage('Unterminated string');

        Nacl::parse('foo="bar');
    }

    /**
     * @test
     */
    public function testUnterminatedHeredoc(): void
    {
        $this->expectException(LexingException::class);
        $this->expectExceptionMessage('Unterminated HEREDOC');

        Nacl::parse("foo=<<<TEST\n");
    }

    /**
     * @test
     */
    public function testUnterminatedMultilineComment(): void
    {
        $this->expectException(LexingException::class);
        $this->expectExceptionMessage('Unterminated multiline comment');

        Nacl::parse('/*');
    }

    /**
     * @test
     */
    public function testParseErrorMessageWithTokenName(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Syntax error, unexpected \'10\' (T_NUM)');

        Nacl::parse('foo bar baz {}10;');
    }

    /**
     * @test
     */
    public function testParseErrorMesageWithoutTokenName(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Syntax error, unexpected \';\'');

        Nacl::parse('+;');
    }

    /**
     * @test
     */
    public function parsingUnexistingFileThrowInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Nacl::parseFile('error');
    }

    /**
     * @test
     */
    public function scalarValueMacroInsideObjectNodeThrowParsingException(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Macro without assignation key must return an object');

        Nacl::parse('foo bar; .env unexistingenv');
    }

    /**
     * @test
     */
    public function contentAfterScalarRootValueThrowsParsingExcpetion(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Syntax error, unexpected \'foo\' (T_NAME)');

        Nacl::parse('.env unexistingenv; foo bar');
    }

    /**
     * @test
     */
    public function testRegisterMacro(): void
    {
        Nacl::registerMacro($macro = new Macros\Callback('strtoupper', fn($p) => strtoupper($p)));
        $this->assertSame(['foo' => 'BAR'], Nacl::parse('foo .strtoupper bar'));
        $this->assertSame(['foo' => 'BAR'], Nacl::parse('${BAR} = .strtoupper bar; foo ${BAR};'));
    }

    /**
     * @test
     */
    public function testLazyMacroExecution(): void
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
    public function refWithoutString(): void
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessage('.ref expects parameter to be string, array given.');

        Nacl::parse('ref .ref {}');
    }

    /**
     * @test
     */
    public function refWithUnexistingStringAndDefaultValue(): void
    {
        $result = Nacl::parse('foo .ref (default: bar) "/app/foo"');

        $this->assertSame([ 'foo' => 'bar' ], $result);
    }

    /**
     * @test
     */
    public function circularDependencyDetection(): void
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessage('Circular dependence detected.');

        Nacl::parse('foo .ref "./bar"; bar .ref "foo"');
    }

    /**
     * @test
     */
    public function undefinedRef(): void
    {
        $this->expectException(ReferenceException::class);
        $this->expectExceptionMessage('Undefined property: bar.');

        Nacl::parse('foo .ref "bar";');
    }

    /**
     * @test
     */
    public function fileMacroWithUnexistingFile(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Unable to read file \'unknown.txt\'');
        Nacl::parse('foo .file "unknown.txt"');
    }

    /**
     * @test
     */
    public function fileMacroWithUnexistingFileAndDefaultValue(): void
    {
        $result = Nacl::parse('foo .file (default: bar) "unknown.txt"');

        $this->assertSame([ 'foo' => 'bar' ], $result);
    }
}
