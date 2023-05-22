<?php

namespace TQ\Shamir\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CliTest extends TestCase
{
    protected $secretUtf8 = 'Lorem ipsum dolor sit असरकारक संस्थान δισεντιας قبضتهم нолюёжжэ 問ナマ業71職げら覧品モス変害';

    protected $secretAscii;

    protected $descriptorSpec;

    protected static $cmd = __DIR__.'/../bin/shamir.php';

    /**
     * Call protected/private method of a class.
     *
     * @param  object  $object      Instantiated object that we will run method on.
     * @param  string  $methodName  Method name to call
     * @param  array   $parameters  Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));

        return $reflection->getMethod($methodName)->invokeArgs($object, $parameters);
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
        $reflection = new ReflectionClass($class);

        return $reflection->getMethod($methodName)->invokeArgs(null, $parameters);
    }

    protected function setUp(): void
    {
        $this->descriptorSpec = [
            1 => ["pipe", "w"], // stdout is a pipe that the child will write to
            2 => ["pipe", "w"], // stderr is a pipe that the child will write to
        ];
    }

    protected function execute($cmd): array
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

    public static function provideUsage(): array
    {
        return [
            [self::$cmd, '.*Usage:.*'],
            [self::$cmd, '.*Available commands:.*'],
            [self::$cmd.' help', '.*Usage:.*'],
            [self::$cmd.' -h', '.*Usage:.*'],
            [self::$cmd.' --help', '.*Usage:.*'],
            [self::$cmd.' list', '.*Usage:.*'],
            [self::$cmd.' list', '.*Available commands:.*'],
            [self::$cmd.' list', '.*Available commands:.*'],
            [self::$cmd.' help shamir:share', '.*Create a shared secret.*'],
            [self::$cmd.' help shamir:recover', '.*Recover a shared secret.*'],
        ];
    }

    #[DataProvider('provideUsage')]
    public function testUsage($cmd, $regexp): void
    {
        $ret = $this->execute($cmd);

        self::assertEquals(0, $ret['ret']);
        self::assertMatchesRegularExpression('('.$regexp.')', $ret['std']);
        self::assertSame('', $ret['err']);
    }

    public function testWrongCommand(): void
    {
        $ret = $this->execute(self::$cmd.' quatsch');

        self::assertEquals(1, $ret['ret']);
        self::assertSame('', $ret['std']);
        self::assertMatchesRegularExpression('(.*Command "quatsch" is not defined..*)', $ret['err']);
    }

    public function testUsageQuiet(): void
    {
        $ret = $this->execute(self::$cmd.' help -q');

        self::assertEquals(0, $ret['ret']);
        self::assertSame('', $ret['std']);
        self::assertSame('', $ret['err']);
    }

    public function testVersion(): void
    {
        $ret = $this->execute(self::$cmd.' -V');

        self::assertEquals(0, $ret['ret']);
        self::assertMatchesRegularExpression('(Shamir\'s Shared Secret CLI.*)', $ret['std']);
    }

    public function testFileInput(): void
    {
        $ret = $this->execute(self::$cmd.' shamir:share -f tests/secret.txt');
        self::assertEquals(0, $ret['ret']);
        self::assertMatchesRegularExpression('(10201.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10202.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10203.*)', $ret['std']);
    }

    public function testStandardInput(): void
    {
        $ret = $this->execute('echo -n "Share my secret" | '.self::$cmd.' shamir:share');
        self::assertEquals(0, $ret['ret']);
        self::assertMatchesRegularExpression('(10201.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10202.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10203.*)', $ret['std']);
    }
}
