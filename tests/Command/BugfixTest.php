<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease\Command;

use Innmind\GitRelease\{
    Command\Bugfix,
    SignedRelease,
    LatestVersion,
    UnsignedRelease,
};
use Innmind\Git\Git;
use Innmind\Immutable\Map;
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\ExitCode,
    Server\Process\Output,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Url\Path;
use Innmind\Stream\{
    Writable,
    Readable,
};
use Innmind\Immutable\Str;
use PHPUnit\Framework\TestCase;

class BugfixTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Bugfix(
                new Git($this->createMock(Server::class)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion
            )
        );
    }

    public function testUsage()
    {
        $this->assertSame(
            "bugfix --no-sign\n\nCreate a new bugfix tag and push it",
            (string) new Bugfix(
                new Git($this->createMock(Server::class)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion
            )
        );
    }

    public function testExitWhenUnknownVersionFormat()
    {
        $command = new Bugfix(
            new Git($server = $this->createMock(Server::class)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion
        );
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
            ->willReturn('v1.0.0|||foo');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/somewhere'));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of("Unsupported tag name format\n"));
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(1);

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options
        ));
    }

    public function testExitWhenEmptyMessage()
    {
        $command = new Bugfix(
            new Git($server = $this->createMock(Server::class)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion
        );
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
            ->willReturn('1.0.0|||foo');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->at(0))
            ->method('write')
            ->with(Str::of("Current release: 1.0.0\n"));
        $output
            ->expects($this->at(1))
            ->method('write')
            ->with(Str::of("Next release: 1.0.1\n"));
        $output
            ->expects($this->at(2))
            ->method('write')
            ->with(Str::of('message: '));
        $input = fopen('php://temp', 'r+');
        fwrite($input, "\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of("Invalid message\n"));
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(1);

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options
        ));
    }

    public function testSignedRelease()
    {
        $command = new Bugfix(
            new Git($server = $this->createMock(Server::class)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion
        );
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
            ->willReturn('1.1.1|||foo');
        $processes
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'tag' '-s' '-a' '1.1.2' '-m' 'watev'" &&
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
        $processes
            ->expects($this->at(3))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'push'" &&
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
        $processes
            ->expects($this->at(4))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'push' '--tags'" &&
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
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->at(0))
            ->method('write')
            ->with(Str::of("Current release: 1.1.1\n"));
        $output
            ->expects($this->at(1))
            ->method('write')
            ->with(Str::of("Next release: 1.1.2\n"));
        $output
            ->expects($this->at(2))
            ->method('write')
            ->with(Str::of('message: '));
        $input = fopen('php://temp', 'r+');
        fwrite($input, "watev\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->never())
            ->method('error');

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options
        ));
    }

    public function testUnsignedRelease()
    {
        $command = new Bugfix(
            new Git($server = $this->createMock(Server::class)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion
        );
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
            ->willReturn('1.1.1|||foo');
        $processes
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'tag' '1.1.2' '-a' '-m' 'watev'" &&
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
        $processes
            ->expects($this->at(3))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'push'" &&
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
        $processes
            ->expects($this->at(4))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'push' '--tags'" &&
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
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->at(0))
            ->method('write')
            ->with(Str::of("Current release: 1.1.1\n"));
        $output
            ->expects($this->at(1))
            ->method('write')
            ->with(Str::of("Next release: 1.1.2\n"));
        $output
            ->expects($this->at(2))
            ->method('write')
            ->with(Str::of('message: '));
        $input = fopen('php://temp', 'r+');
        fwrite($input, "watev\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->never())
            ->method('error');

        $options = new Map('string', 'mixed');
        $options = $options->put('no-sign', true);

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options($options)
        ));
    }
}
