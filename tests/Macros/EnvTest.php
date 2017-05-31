<?php

namespace Nuglif\Nacl\Macros;

class EnvTest extends \PHPUnit\Framework\TestCase
{
    private $macro;

    public function setUp()
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
    }

    /**
     * @test
     */
    public function executeWillForceNumTypeIfDefined()
    {
        putenv('FOO=1k');
        $this->assertSame(1000, $this->macro->execute('FOO', [ 'type' => 'num' ]));
    }

    /**
     * @test
     */
    public function executeWillForceBoolTypeIfDefined()
    {
        putenv('FOO=true');
        $this->assertTrue($this->macro->execute('FOO', [ 'type' => 'bool' ]));
    }

}
