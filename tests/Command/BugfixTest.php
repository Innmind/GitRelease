<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease\Command;

use Innmind\GitRelease\{
    Command\Bugfix,
    SignedRelease,
    LatestVersion,
    UnsignedRelease,
    Release,
};
use Innmind\Git\Git;
use Innmind\Immutable\Map;
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\Output,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
    Console,
};
use Innmind\TimeContinuum\Earth\{
    Clock,
    Timezone\UTC,
};
use Innmind\Immutable\{
    Either,
    SideEffect,
};
use PHPUnit\Framework\TestCase;

class BugfixTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Bugfix(
                new Release(
                    Git::of($this->createMock(Server::class), new Clock(new UTC)),
                    new SignedRelease,
                    new UnsignedRelease,
                    new LatestVersion,
                ),
            ),
        );
    }

    public function testUsage()
    {
        $this->assertSame(
            "bugfix --no-sign --message=\n\nCreate a new bugfix tag and push it",
            (new Bugfix(
                new Release(
                    Git::of($this->createMock(Server::class), new Clock(new UTC)),
                    new SignedRelease,
                    new UnsignedRelease,
                    new LatestVersion,
                ),
            ))->usage(),
        );
    }

    public function testCreateVersionZeroWhenUnknownVersionFormat()
    {
        $command = new Bugfix(
            new Release(
                Git::of($server = $this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $process3 = $this->createMock(Process::class);
        $process4 = $this->createMock(Process::class);
        $process5 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(5))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2, $process3, $process4, $process5) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere/'", $command->toString()),
                    2 => $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    ),
                    3 => $this->assertSame("git 'tag' '0.0.1'", $command->toString()),
                    4 => $this->assertSame("git 'push'", $command->toString()),
                    5 => $this->assertSame("git 'push' '--tags'", $command->toString()),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                    3 => $process3,
                    4 => $process4,
                    5 => $process5,
                };
            });
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('v1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
        $process3
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process4
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process5
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));

        $env = Environment\InMemory::of(
            ["\n"],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of(
            $env,
            new Arguments,
            new Options(Map::of(['no-sign', ''])),
        );

        $env = $command($console)->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 0.0.0\n",
                "Next release: 0.0.1\n",
                'message: ',
            ],
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testExitWhenEmptyMessageWithSignedRelease()
    {
        $command = new Bugfix(
            new Release(
                Git::of($server = $this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere/'", $command->toString()),
                    2 => $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    ),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                };
            });
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');

        $env = Environment\InMemory::of(
            ["\n"],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of($env, new Arguments, new Options);

        $env = $command($console)->environment();

        $this->assertSame(1, $env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.0.0\n",
                "Next release: 1.0.1\n",
                'message: ',
            ],
            $env->outputs(),
        );
        $this->assertSame(["Invalid message\n"], $env->errors());
    }

    public function testExitWhenEmptyMessageWithUnsignedRelease()
    {
        $command = new Bugfix(
            new Release(
                Git::of($server = $this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $process3 = $this->createMock(Process::class);
        $process4 = $this->createMock(Process::class);
        $process5 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(5))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2, $process3, $process4, $process5) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere/'", $command->toString()),
                    2 => $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    ),
                    3 => $this->assertSame("git 'tag' '1.1.2'", $command->toString()),
                    4 => $this->assertSame("git 'push'", $command->toString()),
                    5 => $this->assertSame("git 'push' '--tags'", $command->toString()),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                    3 => $process3,
                    4 => $process4,
                    5 => $process5,
                };
            });
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
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
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process4
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process5
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));

        $env = Environment\InMemory::of(
            ["\n"],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of(
            $env,
            new Arguments,
            new Options(Map::of(['no-sign', ''])),
        );

        $env = $command($console)->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.1.1\n",
                "Next release: 1.1.2\n",
                'message: ',
            ],
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testSignedRelease()
    {
        $command = new Bugfix(
            new Release(
                Git::of($server = $this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $process3 = $this->createMock(Process::class);
        $process4 = $this->createMock(Process::class);
        $process5 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(5))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2, $process3, $process4, $process5) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere/'", $command->toString()),
                    2 => $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    ),
                    3 => $this->assertSame("git 'tag' '-s' '-a' '1.1.2' '-m' 'watev'", $command->toString()),
                    4 => $this->assertSame("git 'push'", $command->toString()),
                    5 => $this->assertSame("git 'push' '--tags'", $command->toString()),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                    3 => $process3,
                    4 => $process4,
                    5 => $process5,
                };
            });
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
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
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process4
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process5
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));

        $env = Environment\InMemory::of(
            ["watev\n"],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of($env, new Arguments, new Options);

        $env = $command($console)->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.1.1\n",
                "Next release: 1.1.2\n",
                'message: ',
            ],
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testUnsignedRelease()
    {
        $command = new Bugfix(
            new Release(
                Git::of($server = $this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $process3 = $this->createMock(Process::class);
        $process4 = $this->createMock(Process::class);
        $process5 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(5))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2, $process3, $process4, $process5) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere/'", $command->toString()),
                    2 => $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    ),
                    3 => $this->assertSame("git 'tag' '1.1.2' '-a' '-m' 'watev'", $command->toString()),
                    4 => $this->assertSame("git 'push'", $command->toString()),
                    5 => $this->assertSame("git 'push' '--tags'", $command->toString()),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                    3 => $process3,
                    4 => $process4,
                    5 => $process5,
                };
            });
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
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
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process4
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process5
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));

        $env = Environment\InMemory::of(
            ["watev\n"],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of(
            $env,
            new Arguments,
            new Options(Map::of(['no-sign', ''])),
        );

        $env = $command($console)->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.1.1\n",
                "Next release: 1.1.2\n",
                'message: ',
            ],
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testSignedReleaseWithMessageOption()
    {
        $command = new Bugfix(
            new Release(
                Git::of($server = $this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $process3 = $this->createMock(Process::class);
        $process4 = $this->createMock(Process::class);
        $process5 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(5))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2, $process3, $process4, $process5) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere/'", $command->toString()),
                    2 => $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    ),
                    3 => $this->assertSame("git 'tag' '-s' '-a' '1.1.2' '-m' 'watev'", $command->toString()),
                    4 => $this->assertSame("git 'push'", $command->toString()),
                    5 => $this->assertSame("git 'push' '--tags'", $command->toString()),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                    3 => $process3,
                    4 => $process4,
                    5 => $process5,
                };
            });
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
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
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process4
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process5
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));

        $env = Environment\InMemory::of(
            [],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of(
            $env,
            new Arguments,
            new Options(Map::of(['message', 'watev'])),
        );

        $env = $command($console)->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.1.1\n",
                "Next release: 1.1.2\n",
            ],
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testExitWhenSignedReleaseWithEmptyMessageOption()
    {
        $command = new Bugfix(
            new Release(
                Git::of($server = $this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere/'", $command->toString()),
                    2 => $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    ),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                };
            });
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('toString')
            ->willReturn('1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');

        $env = Environment\InMemory::of(
            [],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of(
            $env,
            new Arguments,
            new Options(Map::of(['message', ''])),
        );

        $env = $command($console)->environment();

        $this->assertSame(1, $env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.0.0\n",
                "Next release: 1.0.1\n",
            ],
            $env->outputs(),
        );
        $this->assertSame(["Invalid message\n"], $env->errors());
    }

    public function testUnsignedReleaseWithMessageOption()
    {
        $command = new Bugfix(
            new Release(
                Git::of($server = $this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $process3 = $this->createMock(Process::class);
        $process4 = $this->createMock(Process::class);
        $process5 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(5))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2, $process3, $process4, $process5) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere/'", $command->toString()),
                    2 => $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    ),
                    3 => $this->assertSame("git 'tag' '1.1.2' '-a' '-m' 'watev'", $command->toString()),
                    4 => $this->assertSame("git 'push'", $command->toString()),
                    5 => $this->assertSame("git 'push' '--tags'", $command->toString()),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                    3 => $process3,
                    4 => $process4,
                    5 => $process5,
                };
            });
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
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
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process4
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process5
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));

        $env = Environment\InMemory::of(
            [],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of(
            $env,
            new Arguments,
            new Options(Map::of(['no-sign', ''], ['message', 'watev'])),
        );

        $env = $command($console)->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.1.1\n",
                "Next release: 1.1.2\n",
            ],
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testUnsignedReleaseWithEmptyMessageOption()
    {
        $command = new Bugfix(
            new Release(
                Git::of($server = $this->createMock(Server::class), new Clock(new UTC)),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $process3 = $this->createMock(Process::class);
        $process4 = $this->createMock(Process::class);
        $process5 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(5))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2, $process3, $process4, $process5) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere/'", $command->toString()),
                    2 => $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    ),
                    3 => $this->assertSame("git 'tag' '1.1.2'", $command->toString()),
                    4 => $this->assertSame("git 'push'", $command->toString()),
                    5 => $this->assertSame("git 'push' '--tags'", $command->toString()),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                    3 => $process3,
                    4 => $process4,
                    5 => $process5,
                };
            });
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
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
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process4
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process5
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));

        $env = Environment\InMemory::of(
            [],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of(
            $env,
            new Arguments,
            new Options(Map::of(['no-sign', ''], ['message', ''])),
        );

        $env = $command($console)->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.1.1\n",
                "Next release: 1.1.2\n",
            ],
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }
}
