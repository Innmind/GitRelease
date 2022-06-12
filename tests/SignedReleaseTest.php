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
    Server\Processes,
    Server\Process,
};
use Innmind\Url\Path;
use Innmind\TimeContinuum\Earth\{
    Clock,
    Timezone\UTC,
};
use Innmind\Immutable\{
    Either,
    SideEffect,
};
use PHPUnit\Framework\TestCase;

class SignedReleaseTest extends TestCase
{
    public function testInvokation()
    {
        $release = new SignedRelease;
        $server = $this->createMock(Server::class);
        $path = Path::of('/somewhere');
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(4))
            ->method('execute')
            ->withConsecutive(
                [$this->callback(static function($command): bool {
                    return $command->toString() === "mkdir '-p' '/somewhere'";
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'tag' '-s' '-a' '1.0.0' '-m' 'watev'" &&
                        '/somewhere' === $command->workingDirectory()->match(
                            static fn($directory) => $directory->toString(),
                            static fn() => null,
                        );
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push'" &&
                        '/somewhere' === $command->workingDirectory()->match(
                            static fn($directory) => $directory->toString(),
                            static fn() => null,
                        );
                })],
                [$this->callback(static function($command): bool {
                    return $command->toString() === "git 'push' '--tags'" &&
                        '/somewhere' === $command->workingDirectory()->match(
                            static fn($directory) => $directory->toString(),
                            static fn() => null,
                        );
                })],
            )
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
                $process3 = $this->createMock(Process::class),
                $process4 = $this->createMock(Process::class),
            ));
        $process1
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process2
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process3
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process4
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));

        $this->assertNull($release(
            Repository::of($server, $path, new Clock(new UTC))->match(
                static fn($repo) => $repo,
                static fn() => null,
            ),
            new Version(1, 0, 0),
            Message::of('watev'),
        ));
    }
}
