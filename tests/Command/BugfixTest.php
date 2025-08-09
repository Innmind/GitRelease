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
use Innmind\Server\Control\Servers\Mock;
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
    Console,
};
use Innmind\TimeContinuum\Clock;
use Innmind\Immutable\Map;
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
                        Mock::new($this->assert()),
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
                        Mock::new($this->assert()),
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
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere/'",
                $command->toString(),
            ))
            ->willExecute(
                function($command) {
                    $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                },
                static fn($_, $builder) => $builder->success([[
                    'v1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                    'output',
                ]]),
            )
            ->willExecute(fn($command) => $this->assertSame(
                "git 'tag' '0.0.1'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push' '--tags'",
                $command->toString(),
            ));
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
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testExitWhenEmptyMessageWithSignedRelease()
    {
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere/'",
                $command->toString(),
            ))
            ->willExecute(
                function($command) {
                    $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                },
                static fn($_, $builder) => $builder->success([[
                    '1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                    'output',
                ]]),
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

        $env = Environment\InMemory::of(
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
            ],
            $env->outputs(),
        );
        $this->assertSame(["Invalid message\n"], $env->errors());
    }

    public function testExitWhenEmptyMessageWithUnsignedRelease()
    {
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere/'",
                $command->toString(),
            ))
            ->willExecute(
                function($command) {
                    $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                },
                static fn($_, $builder) => $builder->success([[
                    '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                    'output',
                ]]),
            )
            ->willExecute(fn($command) => $this->assertSame(
                "git 'tag' '1.1.2'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push' '--tags'",
                $command->toString(),
            ));
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
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testSignedRelease()
    {
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere/'",
                $command->toString(),
            ))
            ->willExecute(
                function($command) {
                    $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                },
                static fn($_, $builder) => $builder->success([[
                    '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                    'output',
                ]]),
            )
            ->willExecute(fn($command) => $this->assertSame(
                "git 'tag' '-s' '-a' '1.1.2' '-m' 'watev'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push' '--tags'",
                $command->toString(),
            ));
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

        $env = Environment\InMemory::of(
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
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testUnsignedRelease()
    {
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere/'",
                $command->toString(),
            ))
            ->willExecute(
                function($command) {
                    $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                },
                static fn($_, $builder) => $builder->success([[
                    '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                    'output',
                ]]),
            )
            ->willExecute(fn($command) => $this->assertSame(
                "git 'tag' '1.1.2' '-a' '-m' 'watev'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push' '--tags'",
                $command->toString(),
            ));
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
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testSignedReleaseWithMessageOption()
    {
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere/'",
                $command->toString(),
            ))
            ->willExecute(
                function($command) {
                    $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                },
                static fn($_, $builder) => $builder->success([[
                    '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                    'output',
                ]]),
            )
            ->willExecute(fn($command) => $this->assertSame(
                "git 'tag' '-s' '-a' '1.1.2' '-m' 'watev'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push' '--tags'",
                $command->toString(),
            ));
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
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testExitWhenSignedReleaseWithEmptyMessageOption()
    {
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere/'",
                $command->toString(),
            ))
            ->willExecute(
                function($command) {
                    $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                },
                static fn($_, $builder) => $builder->success([[
                    '1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                    'output',
                ]]),
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

        $env = $command($console)->unwrap()->environment();

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
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere/'",
                $command->toString(),
            ))
            ->willExecute(
                function($command) {
                    $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                },
                static fn($_, $builder) => $builder->success([[
                    '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                    'output',
                ]]),
            )
            ->willExecute(fn($command) => $this->assertSame(
                "git 'tag' '1.1.2' '-a' '-m' 'watev'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push' '--tags'",
                $command->toString(),
            ));
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
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }

    public function testUnsignedReleaseWithEmptyMessageOption()
    {
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere/'",
                $command->toString(),
            ))
            ->willExecute(
                function($command) {
                    $this->assertSame(
                        "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)|||%(creatordate:rfc2822)'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                },
                static fn($_, $builder) => $builder->success([[
                    '1.1.1|||foo|||Sat, 16 Mar 2019 12:09:24 +0100',
                    'output',
                ]]),
            )
            ->willExecute(fn($command) => $this->assertSame(
                "git 'tag' '1.1.2'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push' '--tags'",
                $command->toString(),
            ));
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
            $env->outputs(),
        );
        $this->assertSame([], $env->errors());
    }
}
