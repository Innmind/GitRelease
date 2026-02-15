<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease;

use Innmind\GitRelease\{
    SignedRelease,
    Version,
};
use Innmind\Git\{
    Repository,
    Message,
};
use Innmind\Server\Control\{
    Server,
    Server\Process\Builder,
};
use Innmind\Url\Path;
use Innmind\Time\Clock;
use Innmind\Immutable\{
    Attempt,
    SideEffect,
};
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class SignedReleaseTest extends TestCase
{
    public function testInvokation()
    {
        $release = new SignedRelease;
        $count = 0;
        $server = Server::via(
            function($command) use (&$count) {
                $this->assertSame(
                    match ($count) {
                        0 => "mkdir '-p' '/somewhere'",
                        1 => "git 'tag' '-s' '-a' '1.0.0' '-m' 'watev'",
                        2 => "git 'push'",
                        3 => "git 'push' '--tags'",
                    },
                    $command->toString(),
                );

                $builder = Builder::foreground(2 + $count);

                if ($count === 1) {
                    $this->assertSame('/somewhere', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                ++$count;

                return Attempt::result($builder->build());
            },
        );
        $path = Path::of('/somewhere');

        $this->assertInstanceOf(
            SideEffect::class,
            $release(
                Repository::of(
                    $server,
                    $path,
                    Clock::live()->switch(static fn($timezones) => $timezones->utc()),
                )->match(
                    static fn($repo) => $repo,
                    static fn() => null,
                ),
                Version::of('1.0.0')->match(
                    static fn($version) => $version,
                    static fn() => null,
                ),
                Message::of('watev'),
            )->match(
                static fn($sideEffect) => $sideEffect,
                static fn() => null,
            ),
        );
    }
}
