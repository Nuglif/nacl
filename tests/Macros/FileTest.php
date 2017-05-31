<?php

namespace Nuglif\Nacl\Macros;

use Phake;

class FileTest extends \PHPUnit\Framework\TestCase
{
    private $macro;
    private $parser;

    public function setUp()
    {
        $this->macro = new File;
        $this->parser = $this->getMockBuilder(\Nuglif\Nacl\Parser::class)
            ->getMock();

        $this->macro->setParser($this->parser);
    }

    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function executeCallParseErrorIfFileDoesntExists()
    {
        $this->parser->method('error')->will($this->throwException(new \InvalidArgumentException));
        $this->parser->method('resolvePath')->will($this->returnValue(false));

        $this->macro->execute('test');
    }

    /**
     * @test
     */
    public function executeReturnTheFileContent()
    {
        $this->parser->method('resolvePath')->with('foo')->will($this->returnValue(__FILE__));

        $this->assertSame(file_get_contents(__FILE__), $this->macro->execute('foo'));
    }
}
