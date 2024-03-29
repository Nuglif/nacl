<?php

declare(strict_types=1);

namespace Nuglif\Nacl\Macros;

class ConstantTest extends \PHPUnit\Framework\TestCase
{
    public const FOOBAR = 'foo';

    private Constant $macro;

    public function setUp(): void
    {
        $this->macro = new Constant();
    }

    /**
     * @test
     */
    public function executeReturnConstant(): void
    {
        $this->assertSame('foo', $this->macro->execute(self::class . '::FOOBAR', []));
    }

    /**
     * @test
     */
    public function executeWillReturnDefaultValueIfConstantDoNotExists(): void
    {
        $default = 29303;
        $this->assertSame($default, $this->macro->execute('UNEXISTING_CONST', [ 'default' => $default ]));
    }

    /**
     * @test
     */
    public function executeWillReturnInvalidArgumentExceptionIfNameIsNotAString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->macro->execute(10);
    }
}
