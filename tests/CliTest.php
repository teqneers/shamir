<?php

namespace TQ\Shamir\Tests;

use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    protected $secretUtf8 = 'Lorem ipsum dolor sit असरकारक संस्थान δισεντιας قبضتهم нолюёжжэ 問ナマ業71職げら覧品モス変害';

    protected $secretAscii;

    protected $descriptorSpec;

    protected $cmd;

    function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->cmd = __DIR__.'/../bin/shamir.php';
    }

    /**
     * Call protected/private method of a class.
     *
     * @param  object &$object      Instantiated object that we will run method on.
     * @param  string  $methodName  Method name to call
     * @param  array   $parameters  Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Call protected/private static method of a class.
     *
     * @param  string  $class       Name of the class
     * @param  string  $methodName  Static method name to call
     * @param  array   $parameters  Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeStaticMethod($class, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($class);
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $parameters);
    }

    protected function setUp()
    {
        $this->descriptorSpec = [
            1 => ["pipe", "w"], // stdout is a pipe that the child will write to
            2 => ["pipe", "w"] // stderr is a pipe that the child will write to
        ];
    }

    protected function execute($cmd)
    {
        $ret = [];

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
        return [
            [$this->cmd, '.*Usage:.*'],
            [$this->cmd, '.*Available commands:.*'],
            [$this->cmd.' help', '.*Usage:.*'],
            [$this->cmd.' -h', '.*Usage:.*'],
            [$this->cmd.' --help', '.*Usage:.*'],
            [$this->cmd.' list', '.*Usage:.*'],
            [$this->cmd.' list', '.*Available commands:.*'],
            [$this->cmd.' list', '.*Available commands:.*'],
            [$this->cmd.' help shamir:share', '.*Create a shared secret.*'],
            [$this->cmd.' help shamir:recover', '.*Recover a shared secret.*'],
        ];
    }

    /**
     * @dataProvider providerUsage
     */
    public function testUsage($cmd, $regexp)
    {
        $ret = $this->execute($cmd);

        $this->assertEquals(0, $ret['ret']);
        $this->assertRegExp('('.$regexp.')', $ret['std']);
        $this->assertSame('', $ret['err']);
    }

    public function testWrongCommand()
    {
        $ret = $this->execute($this->cmd.' quatsch');

        $this->assertEquals(1, $ret['ret']);
        $this->assertSame('', $ret['std']);
        $this->assertRegExp('(.*Command "quatsch" is not defined..*)', $ret['err']);
    }

    public function testUsageQuiet()
    {
        $ret = $this->execute($this->cmd.' help -q');

        $this->assertEquals(0, $ret['ret']);
        $this->assertSame('', $ret['std']);
        $this->assertSame('', $ret['err']);
    }

    public function testVersion()
    {
        $ret = $this->execute($this->cmd.' -V');

        $this->assertEquals(0, $ret['ret']);
        $this->assertRegExp('(Shamir\'s Shared Secret CLI.*)', $ret['std']);
    }

    public function testFileInput()
    {
        $ret = $this->execute($this->cmd.' shamir:share -f tests/secret.txt');
        $this->assertEquals(0, $ret['ret']);
        $this->assertRegExp('(10201.*)', $ret['std']);
        $this->assertRegExp('(10202.*)', $ret['std']);
        $this->assertRegExp('(10203.*)', $ret['std']);
    }

    public function testStandardInput()
    {
        $ret = $this->execute('echo -n "Share my secret" | '.$this->cmd.' shamir:share');
        $this->assertEquals(0, $ret['ret']);
        $this->assertRegExp('(10201.*)', $ret['std']);
        $this->assertRegExp('(10202.*)', $ret['std']);
        $this->assertRegExp('(10203.*)', $ret['std']);
    }
}
