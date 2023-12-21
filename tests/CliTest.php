<?php

namespace TQ\Shamir\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    protected $secretUtf8 = 'Lorem ipsum dolor sit असरकारक संस्थान δισεντιας قبضتهم нолюёжжэ 問ナマ業71職げら覧品モス変害';

    protected $secretAscii;

    protected $descriptorSpec;

    protected static $cmd = __DIR__.'/../bin/shamir.php';

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

        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
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

        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
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
        $cmd = self::$cmd.' '.escapeshellarg('shamir:share').' '.escapeshellarg('-f').' '.escapeshellarg('tests/secret.txt');
        $ret = $this->execute($cmd);
        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
        self::assertMatchesRegularExpression('(10201.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10202.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10203.*)', $ret['std']);
    }

    public function testStandardInput(): void
    {
        $ret = $this->execute('echo -n "Share my secret" | '.self::$cmd.' shamir:share');
        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
        self::assertMatchesRegularExpression('(10201.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10202.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10203.*)', $ret['std']);
    }
}
