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
use Innmind\Server\Control\Servers\Mock;
use Innmind\Url\Path;
use Innmind\TimeContinuum\Clock;
use Innmind\Immutable\SideEffect;
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class SignedReleaseTest extends TestCase
{
    public function testInvokation()
    {
        $release = new SignedRelease;
        $server = Mock::new($this->assert())
            ->willExecute(fn($command) => $this->assertSame(
                "mkdir '-p' '/somewhere'",
                $command->toString(),
            ))
            ->willExecute(function($command) {
                $this->assertSame(
                    "git 'tag' '-s' '-a' '1.0.0' '-m' 'watev'",
                    $command->toString(),
                );
                $this->assertSame('/somewhere', $command->workingDirectory()->match(
                    static fn($directory) => $directory->toString(),
                    static fn() => null,
                ));
            })
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push'",
                $command->toString(),
            ))
            ->willExecute(fn($command) => $this->assertSame(
                "git 'push' '--tags'",
                $command->toString(),
            ));
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
