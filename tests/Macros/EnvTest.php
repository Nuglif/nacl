<?php

declare(strict_types=1);

namespace Nuglif\Nacl\Macros;

class EnvTest extends \PHPUnit\Framework\TestCase
{
    private Env $macro;

    public function setUp(): void
    {
        $this->macro = new Env();
    }

    /**
     * @test
     */
    public function executeReturnTheEnvVarAsIs(): void
    {
        putenv('FOO=BAR');
        $this->assertSame('BAR', $this->macro->execute('FOO', []));
    }

    /**
     * @test
     */
    public function executeReturnFalseIfEnvVarIsNotset(): void
    {
        $this->assertFalse($this->macro->execute('NOT_SET'));
    }

    /**
     * @test
     */
    public function executeReturnDefaultWhenEnvVarIsNotSetAndDefaultIsSet(): void
    {
        $this->assertSame('FOO', $this->macro->execute('NOT_SET', [ 'default' => 'FOO' ]));
        $this->assertSame(null, $this->macro->execute('NOT_SET', [ 'default' => null ]));
    }

    /**
     * @test
     */
    public function executeWillForceNumTypeIfDefined(): void
    {
        putenv('FOO=10.1');
        $this->assertSame(10.1, $this->macro->execute('FOO', [ 'type' => 'num' ]));
        $this->assertSame(10.1, $this->macro->execute('FOO', [ 'type' => 'numeric' ]));
    }

    /**
     * @test
     */
    public function executeWillForceBoolTypeIfDefined(): void
    {
        putenv('FOO=true');
        $this->assertTrue($this->macro->execute('FOO', [ 'type' => 'bool' ]));
        $this->assertTrue($this->macro->execute('FOO', [ 'type' => 'boolean' ]));
    }

    /**
     * @test
     */
    public function executeWillForceIntTypeIfDefined(): void
    {
        putenv('FOO=10.1');
        $this->assertSame(10, $this->macro->execute('FOO', [ 'type' => 'int' ]));
        $this->assertSame(10, $this->macro->execute('FOO', [ 'type' => 'integer' ]));
    }

    /**
     * @test
     */
    public function executeWillThrowInvalidArgumentExceptionIfTypeIsUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        putenv('FOO=foo');
        $this->macro->execute('FOO', [ 'type' => 'unknown' ]);
    }
}
