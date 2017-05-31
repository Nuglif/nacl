<?php

namespace Nuglif\Nacl\Macros;

class ConstantTest extends \PHPUnit\Framework\TestCase
{
    const FOOBAR = 'foo';

    private $macro;

    public function setUp()
    {
        $this->macro = new Constant;
    }

    /**
     * @test
     */
    public function executeReturnConstant()
    {
        $this->assertSame('foo', $this->macro->execute(self::class . '::FOOBAR', []));
    }

    /**
     * @test
     */
    public function executeWillReturnDefaultValueIfConstantDoNotExists()
    {
        $default = 29303;
        $this->assertSame($default, $this->macro->execute('UNEXISTING_CONST', [ 'default' => $default ]));
    }

    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function executeWillReturnInvalidArgumentExceptionIfNameIsNotAString()
    {
        $this->macro->execute(10);
    }
}
