<?php

namespace Nuglif\Nacl\Macros;

class EnvTest extends \PHPUnit\Framework\TestCase
{
    private $macro;

    public function setUp(): void
    {
        $this->macro = new Env;
    }

    /**
     * @test
     */
    public function executeReturnTheEnvVarAsIs()
    {
        putenv('FOO=BAR');
        $this->assertSame('BAR', $this->macro->execute('FOO', []));
    }

    /**
     * @test
     */
    public function executeReturnFalseIfEnvVarIsNotset()
    {
        $this->assertFalse($this->macro->execute('NOT_SET'));
    }

    /**
     * @test
     */
    public function executeReturnDefaultWhenEnvVarIsNotSetAndDefaultIsSet()
    {
        $this->assertSame('FOO', $this->macro->execute('NOT_SET', [ 'default' => 'FOO' ]));
        $this->assertSame(null, $this->macro->execute('NOT_SET', [ 'default' => null ]));
    }

    /**
     * @test
     */
    public function executeWillForceNumTypeIfDefined()
    {
        putenv('FOO=10.1');
        $this->assertSame(10.1, $this->macro->execute('FOO', [ 'type' => 'num' ]));
        $this->assertSame(10.1, $this->macro->execute('FOO', [ 'type' => 'numeric' ]));
    }

    /**
     * @test
     */
    public function executeWillForceBoolTypeIfDefined()
    {
        putenv('FOO=true');
        $this->assertTrue($this->macro->execute('FOO', [ 'type' => 'bool' ]));
        $this->assertTrue($this->macro->execute('FOO', [ 'type' => 'boolean' ]));
    }

    /**
     * @test
     */
    public function executeWillForceIntTypeIfDefined()
    {
        putenv('FOO=10.1');
        $this->assertSame(10, $this->macro->execute('FOO', [ 'type' => 'int' ]));
        $this->assertSame(10, $this->macro->execute('FOO', [ 'type' => 'integer' ]));
    }

    /**
     * @test
     */
    public function executeWillThrowInvalidArgumentExceptionIfTypeIsUnknown()
    {
        $this->expectException(\InvalidArgumentException::class);
        putenv('FOO=foo');
        $this->macro->execute('FOO', [ 'type' => 'unknown' ]);
    }
}
