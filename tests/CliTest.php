<?php

namespace TQ\Shamir\Tests;


use TQ\Shamir\Algorithm\Algorithm;
use TQ\Shamir\Algorithm\RandomGeneratorAware;
use TQ\Shamir\Algorithm\Shamir;
use TQ\Shamir\Random\Generator;
use TQ\Shamir\Secret;

class CliTest extends \PHPUnit_Framework_TestCase
{
    protected $secretUtf8 = 'Lorem ipsum dolor sit असरकारक संस्थान δισεντιας قبضتهم нолюёжжэ 問ナマ業71職げら覧品モス変害';
    protected $secretAscii;
    protected $descriptorSpec;
    protected $cmd;

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Call protected/private static method of a class.
     *
     * @param string $class Name of the class
     * @param string $methodName Static method name to call
     * @param array $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeStaticMethod($class, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass($class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $parameters);
    }

    protected function setUp()
    {
        $this->cmd = __DIR__ . '/../bin/shamir.php';
        $this->descriptorSpec = array(
            1 => array("pipe", "w"), // stdout is a pipe that the child will write to
            2 => array("pipe", "w") // stderr is a pipe that the child will write to
        );

    }

    protected function execute($cmd)
    {
        $ret = array();
        $process = proc_open($cmd, $this->descriptorSpec, $pipes);
        if (is_resource($process)) {
            $ret['std'] = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $ret['err'] = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $ret['ret'] = proc_close($process);
        }

        return $ret;
    }


    public function providerUsage()
    {
        return array(
            array($this->cmd, '.*Usage:.*' ),
            array($this->cmd, '.*Available commands:.*' ),
            array($this->cmd . ' help', '.*Usage:.*'),
            array($this->cmd . ' -h', '.*Usage:.*'),
            array($this->cmd . ' --help', '.*Usage:.*'),
            array($this->cmd . ' list', '.*Usage:.*'),
            array($this->cmd . ' list', '.*Available commands:.*'),
            array($this->cmd . ' list', '.*Available commands:.*'),
            array($this->cmd . ' help shamir:share', '.*Create a shared secret.*'),
            array($this->cmd . ' help shamir:recover', '.*Recover a shared secret.*'),
        );
    }

    /**
     * @dataProvider providerUsage
     */
    public function testUsage( $cmd, $regexp )
    {
        $ret = $this->execute($cmd);

        $this->assertEquals(0, $ret['ret']);
        $this->assertRegExp('('.$regexp.')', $ret['std']);
        $this->assertSame('', $ret['err']);

    }

    public function testWrongCommand( )
    {
        $ret = $this->execute($this->cmd.' quatsch');

        $this->assertEquals(1, $ret['ret']);
        $this->assertSame('', $ret['std']);
        $this->assertRegExp('(.*Command "quatsch" is not defined..*)', $ret['err']);

    }

    public function testUsageQuiet( )
    {
        $ret = $this->execute($this->cmd.' help -q');

        $this->assertEquals(0, $ret['ret']);
        $this->assertSame('', $ret['std']);
        $this->assertSame('', $ret['err']);

    }

    public function testVersion()
    {
        $ret = $this->execute($this->cmd . ' -V');

        $this->assertEquals(0, $ret['ret']);
        $this->assertRegExp('(Shamir\'s Shared Secret CLI.*)', $ret['std']);

    }

}
