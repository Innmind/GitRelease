<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease\Command;

use Innmind\GitRelease\{
    Command\Minor,
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
use Innmind\OperatingSystem\Sockets;
use Innmind\Url\Path;
use Innmind\Stream\{
    Writable,
    Readable,
    Watch\Select,
};
use Innmind\TimeContinuum\Earth\{
    Clock,
    Timezone\UTC,
    ElapsedPeriod,
};
use Innmind\Immutable\{
    Str,
    Sequence,
};
use PHPUnit\Framework\TestCase;

class MinorTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Minor(
                new Git($this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
                $this->createMock(Sockets::class),
            ),
        );
    }

    public function testUsage()
    {
        $this->assertSame(
            "minor --no-sign --message=\n\nCreate a new minor tag and push it",
            (new Minor(
                new Git($this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
                $this->createMock(Sockets::class),
            ))->toString(),
        );
    }

    public function testExitWhenUnknownVersionFormat()
    {
        $command = new Minor(
            new Git($server = $this->createMock(Server::class), new Clock(new UTC)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion,
            $sockets = $this->createMock(Sockets::class),
        );
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->willReturn(new Select(new ElapsedPeriod(1000)));
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait');
        $process1
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('wait');
        $process2
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('v1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
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
            new Options,
        ));
    }

    public function testExitWhenEmptyMessageWithSignedRelease()
    {
        $command = new Minor(
            new Git($server = $this->createMock(Server::class), new Clock(new UTC)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion,
            $sockets = $this->createMock(Sockets::class),
        );
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->willReturn(new Select(new ElapsedPeriod(1000)));
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait');
        $process1
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('wait');
        $process2
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(3))
            ->method('write')
            ->withConsecutive(
                [Str::of("Current release: 1.0.0\n")],
                [Str::of("Next release: 1.1.0\n")],
                [Str::of('message: ')],
            );
        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "\n");
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
            new Options,
        ));
    }

    public function testExitWhenEmptyMessageWithUnsignedRelease()
    {
        $command = new Minor(
            new Git($server = $this->createMock(Server::class), new Clock(new UTC)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion,
            $sockets = $this->createMock(Sockets::class),
        );
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->willReturn(new Select(new ElapsedPeriod(1000)));
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(5))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '1.2.0'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push' '--tags'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
                $process3 = $this->createMock(Process::class),
                $process4 = $this->createMock(Process::class),
                $process5 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait');
        $process1
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('wait');
        $process2
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $process3
            ->expects($this->once())
            ->method('wait');
        $process3
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process4
            ->expects($this->once())
            ->method('wait');
        $process4
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process5
            ->expects($this->once())
            ->method('wait');
        $process5
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(3))
            ->method('write')
            ->withConsecutive(
                [Str::of("Current release: 1.1.1\n")],
                [Str::of("Next release: 1.2.0\n")],
                [Str::of('message: ')],
            );
        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->never())
            ->method('error');

        $options = Map::of('string', 'string');
        $options = $options->put('no-sign', '');

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options($options),
        ));
    }

    public function testSignedRelease()
    {
        $command = new Minor(
            new Git($server = $this->createMock(Server::class), new Clock(new UTC)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion,
            $sockets = $this->createMock(Sockets::class),
        );
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->willReturn(new Select(new ElapsedPeriod(1000)));
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(5))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '-s' '-a' '1.2.0' '-m' 'watev'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push' '--tags'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
                $process3 = $this->createMock(Process::class),
                $process4 = $this->createMock(Process::class),
                $process5 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait');
        $process1
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('wait');
        $process2
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $process3
            ->expects($this->once())
            ->method('wait');
        $process3
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process4
            ->expects($this->once())
            ->method('wait');
        $process4
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process5
            ->expects($this->once())
            ->method('wait');
        $process5
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(3))
            ->method('write')
            ->withConsecutive(
                [Str::of("Current release: 1.1.1\n")],
                [Str::of("Next release: 1.2.0\n")],
                [Str::of('message: ')],
            );
        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "watev\n");
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
            new Options,
        ));
    }

    public function testUnsignedRelease()
    {
        $command = new Minor(
            new Git($server = $this->createMock(Server::class), new Clock(new UTC)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion,
            $sockets = $this->createMock(Sockets::class),
        );
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->willReturn(new Select(new ElapsedPeriod(1000)));
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(5))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '1.2.0' '-a' '-m' 'watev'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push' '--tags'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
                $process3 = $this->createMock(Process::class),
                $process4 = $this->createMock(Process::class),
                $process5 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait');
        $process1
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('wait');
        $process2
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $process3
            ->expects($this->once())
            ->method('wait');
        $process3
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process4
            ->expects($this->once())
            ->method('wait');
        $process4
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process5
            ->expects($this->once())
            ->method('wait');
        $process5
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(3))
            ->method('write')
            ->withConsecutive(
                [Str::of("Current release: 1.1.1\n")],
                [Str::of("Next release: 1.2.0\n")],
                [Str::of('message: ')],
            );
        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "watev\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->never())
            ->method('error');

        $options = Map::of('string', 'string');
        $options = $options->put('no-sign', '');

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options($options),
        ));
    }

    public function testSignedReleaseWithMessageOption()
    {
        $command = new Minor(
            new Git($server = $this->createMock(Server::class), new Clock(new UTC)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion,
            $sockets = $this->createMock(Sockets::class),
        );
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->willReturn(new Select(new ElapsedPeriod(1000)));
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(5))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '-s' '-a' '1.2.0' '-m' 'watev'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push' '--tags'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
                $process3 = $this->createMock(Process::class),
                $process4 = $this->createMock(Process::class),
                $process5 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait');
        $process1
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('wait');
        $process2
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $process3
            ->expects($this->once())
            ->method('wait');
        $process3
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process4
            ->expects($this->once())
            ->method('wait');
        $process4
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process5
            ->expects($this->once())
            ->method('wait');
        $process5
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(2))
            ->method('write')
            ->withConsecutive(
                [Str::of("Current release: 1.1.1\n")],
                [Str::of("Next release: 1.2.0\n")],
            );
        $env
            ->expects($this->never())
            ->method('error');

        $options = Map::of('string', 'string');
        $options = $options->put('message', 'watev');

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options($options),
        ));
    }

    public function testExitWhenSignedReleaseWithEmptyMessageOption()
    {
        $command = new Minor(
            new Git($server = $this->createMock(Server::class), new Clock(new UTC)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion,
            $sockets = $this->createMock(Sockets::class),
        );
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->willReturn(new Select(new ElapsedPeriod(1000)));
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait');
        $process1
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('wait');
        $process2
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(2))
            ->method('write')
            ->withConsecutive(
                [Str::of("Current release: 1.0.0\n")],
                [Str::of("Next release: 1.1.0\n")],
            );
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

        $options = Map::of('string', 'string');
        $options = $options->put('message', '');

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options($options),
        ));
    }

    public function testUnsignedReleaseWithMessageOption()
    {
        $command = new Minor(
            new Git($server = $this->createMock(Server::class), new Clock(new UTC)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion,
            $sockets = $this->createMock(Sockets::class),
        );
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->willReturn(new Select(new ElapsedPeriod(1000)));
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(5))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '1.2.0' '-a' '-m' 'watev'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push' '--tags'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
                $process3 = $this->createMock(Process::class),
                $process4 = $this->createMock(Process::class),
                $process5 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait');
        $process1
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('wait');
        $process2
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $process3
            ->expects($this->once())
            ->method('wait');
        $process3
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process4
            ->expects($this->once())
            ->method('wait');
        $process4
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process5
            ->expects($this->once())
            ->method('wait');
        $process5
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(2))
            ->method('write')
            ->withConsecutive(
                [Str::of("Current release: 1.1.1\n")],
                [Str::of("Next release: 1.2.0\n")],
            );
        $env
            ->expects($this->never())
            ->method('error');

        $options = Map::of('string', 'string');
        $options = $options->put('no-sign', '');
        $options = $options->put('message', 'watev');

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options($options),
        ));
    }

    public function testUnsignedReleaseWithEmptyMessageOption()
    {
        $command = new Minor(
            new Git($server = $this->createMock(Server::class), new Clock(new UTC)),
            new SignedRelease,
            new UnsignedRelease,
            new LatestVersion,
            $sockets = $this->createMock(Sockets::class),
        );
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->willReturn(new Select(new ElapsedPeriod(1000)));
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(5))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '1.2.0'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push' '--tags'" &&
                        $command->workingDirectory()->toString() === '/somewhere';
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
                $process3 = $this->createMock(Process::class),
                $process4 = $this->createMock(Process::class),
                $process5 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait');
        $process1
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('wait');
        $process2
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $process3
            ->expects($this->once())
            ->method('wait');
        $process3
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process4
            ->expects($this->once())
            ->method('wait');
        $process4
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process5
            ->expects($this->once())
            ->method('wait');
        $process5
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->exactly(2))
            ->method('write')
            ->withConsecutive(
                [Str::of("Current release: 1.1.1\n")],
                [Str::of("Next release: 1.2.0\n")],
            );
        $env
            ->expects($this->never())
            ->method('error');

        $options = Map::of('string', 'string');
        $options = $options->put('no-sign', '');
        $options = $options->put('message', '');

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options($options),
        ));
    }
}
