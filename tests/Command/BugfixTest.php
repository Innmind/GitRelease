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
use Innmind\Server\Control\{
    Server,
    Server\Process\Builder,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
    Console,
};
use Innmind\Time\Clock;
use Innmind\Immutable\{
    Map,
    Attempt,
};
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class BugfixTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Bugfix(
                new Release(
                    Git::of(
                        Server::via(static fn() => null),
                        Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                    ),
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
            "bugfix --no-sign --message= --help --no-interaction\n\nCreate a new bugfix tag and push it",
            (new Bugfix(
                new Release(
                    Git::of(
                        Server::via(static fn() => null),
                        Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                    ),
                    new SignedRelease,
                    new UnsignedRelease,
                    new LatestVersion,
                ),
            ))->usage()->toString(),
        );
    }

    public function testCreateVersionZeroWhenUnknownVersionFormat()
    {
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere/'",
                        1 => "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        2 => "git 'tag' '0.0.1'",
                        3 => "git 'push'",
                        4 => "git 'push' '--tags'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                    $builder = $builder->success([[
                        'v1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                        'output',
                    ]]);
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $command = new Bugfix(
            new Release(
                Git::of(
                    $server,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                ),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );

        $env = Environment::inMemory(
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

        $env = $command($console)->unwrap()->environment();

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
            $env
                ->outputted()
                ->map(static fn($pair) => $pair[0]->toString())
                ->toList(),
        );
    }

    public function testExitWhenEmptyMessageWithSignedRelease()
    {
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere/'",
                        1 => "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                    $builder = $builder->success([[
                        '1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                        'output',
                    ]]);
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $command = new Bugfix(
            new Release(
                Git::of(
                    $server,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                ),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );

        $env = Environment::inMemory(
            ["\n"],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of($env, new Arguments, new Options);

        $env = $command($console)->unwrap()->environment();

        $this->assertSame(1, $env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.0.0\n",
                "Next release: 1.0.1\n",
                'message: ',
                "Invalid message\n",
            ],
            $env
                ->outputted()
                ->map(static fn($pair) => $pair[0]->toString())
                ->toList(),
        );
    }

    public function testExitWhenEmptyMessageWithUnsignedRelease()
    {
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere/'",
                        1 => "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        2 => "git 'tag' '1.1.2'",
                        3 => "git 'push'",
                        4 => "git 'push' '--tags'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                    $builder = $builder->success([[
                        '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                        'output',
                    ]]);
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $command = new Bugfix(
            new Release(
                Git::of(
                    $server,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                ),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );

        $env = Environment::inMemory(
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

        $env = $command($console)->unwrap()->environment();

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
            $env
                ->outputted()
                ->map(static fn($pair) => $pair[0]->toString())
                ->toList(),
        );
    }

    public function testSignedRelease()
    {
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere/'",
                        1 => "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        2 => "git 'tag' '-s' '-a' '1.1.2' '-m' 'watev'",
                        3 => "git 'push'",
                        4 => "git 'push' '--tags'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                    $builder = $builder->success([[
                        '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                        'output',
                    ]]);
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $command = new Bugfix(
            new Release(
                Git::of(
                    $server,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                ),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );

        $env = Environment::inMemory(
            ["watev\n"],
            true,
            [],
            [],
            '/somewhere',
        );
        $console = Console::of($env, new Arguments, new Options);

        $env = $command($console)->unwrap()->environment();

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
            $env
                ->outputted()
                ->map(static fn($pair) => $pair[0]->toString())
                ->toList(),
        );
    }

    public function testUnsignedRelease()
    {
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere/'",
                        1 => "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        2 => "git 'tag' '1.1.2' '-a' '-m' 'watev'",
                        3 => "git 'push'",
                        4 => "git 'push' '--tags'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                    $builder = $builder->success([[
                        '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                        'output',
                    ]]);
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $command = new Bugfix(
            new Release(
                Git::of(
                    $server,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                ),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );

        $env = Environment::inMemory(
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

        $env = $command($console)->unwrap()->environment();

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
            $env
                ->outputted()
                ->map(static fn($pair) => $pair[0]->toString())
                ->toList(),
        );
    }

    public function testSignedReleaseWithMessageOption()
    {
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere/'",
                        1 => "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        2 => "git 'tag' '-s' '-a' '1.1.2' '-m' 'watev'",
                        3 => "git 'push'",
                        4 => "git 'push' '--tags'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                    $builder = $builder->success([[
                        '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                        'output',
                    ]]);
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $command = new Bugfix(
            new Release(
                Git::of(
                    $server,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                ),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );

        $env = Environment::inMemory(
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

        $env = $command($console)->unwrap()->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.1.1\n",
                "Next release: 1.1.2\n",
            ],
            $env
                ->outputted()
                ->map(static fn($pair) => $pair[0]->toString())
                ->toList(),
        );
    }

    public function testExitWhenSignedReleaseWithEmptyMessageOption()
    {
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere/'",
                        1 => "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                    $builder = $builder->success([[
                        '1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                        'output',
                    ]]);
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $command = new Bugfix(
            new Release(
                Git::of(
                    $server,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                ),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );

        $env = Environment::inMemory(
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

        $env = $command($console)->unwrap()->environment();

        $this->assertSame(1, $env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.0.0\n",
                "Next release: 1.0.1\n",
                "Invalid message\n",
            ],
            $env
                ->outputted()
                ->map(static fn($pair) => $pair[0]->toString())
                ->toList(),
        );
    }

    public function testUnsignedReleaseWithMessageOption()
    {
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere/'",
                        1 => "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        2 => "git 'tag' '1.1.2' '-a' '-m' 'watev'",
                        3 => "git 'push'",
                        4 => "git 'push' '--tags'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                    $builder = $builder->success([[
                        '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                        'output',
                    ]]);
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $command = new Bugfix(
            new Release(
                Git::of(
                    $server,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                ),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );

        $env = Environment::inMemory(
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

        $env = $command($console)->unwrap()->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.1.1\n",
                "Next release: 1.1.2\n",
            ],
            $env
                ->outputted()
                ->map(static fn($pair) => $pair[0]->toString())
                ->toList(),
        );
    }

    public function testUnsignedReleaseWithEmptyMessageOption()
    {
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere/'",
                        1 => "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        2 => "git 'tag' '1.1.2'",
                        3 => "git 'push'",
                        4 => "git 'push' '--tags'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                    $builder = $builder->success([[
                        '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                        'output',
                    ]]);
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $command = new Bugfix(
            new Release(
                Git::of(
                    $server,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                ),
                new SignedRelease,
                new UnsignedRelease,
                new LatestVersion,
            ),
        );

        $env = Environment::inMemory(
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

        $env = $command($console)->unwrap()->environment();

        $this->assertNull($env->exitCode()->match(
            static fn($exit) => $exit->toInt(),
            static fn() => null,
        ));
        $this->assertSame(
            [
                "Current release: 1.1.1\n",
                "Next release: 1.1.2\n",
            ],
            $env
                ->outputted()
                ->map(static fn($pair) => $pair[0]->toString())
                ->toList(),
        );
    }
}
