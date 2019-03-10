<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease;

use Innmind\GitRelease\{
    LatestVersion,
    Version,
    Exception\UnknownVersionFormat,
};
use Innmind\Git\Repository;
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\ExitCode,
    Server\Process\Output,
};
use Innmind\Url\Path;
use PHPUnit\Framework\TestCase;

class LatestVersionTest extends TestCase
{
    public function testWhenNoTag()
    {
        $version = $this->fromOutput('');

        $this->assertInstanceOf(Version::class, $version);
        $this->assertSame('0.0.0', (string) $version);
    }

    public function testLatestVersion()
    {
        $version = $this->fromOutput("1.0.0|||foo\n1.10.0|||bar\n1.2.0|||baz");

        $this->assertInstanceOf(Version::class, $version);
        $this->assertSame('1.10.0', (string) $version);
    }

    public function testThrowWhenUnknownFormat()
    {
        $this->expectException(UnknownVersionFormat::class);
        $this->expectExceptionMessage('v1.0.0');

        $this->fromOutput('v1.0.0|||foo');
    }

    private function fromOutput(string $data)
    {
        $latestVersion = new LatestVersion;
        $server = $this->createMock(Server::class);
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "mkdir '-p' '/somewhere'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)'" &&
                    $command->workingDirectory() === '/somewhere';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn($data);
        $repository = new Repository(
            $server,
            new Path('/somewhere')
        );

        return $latestVersion($repository);
    }
}