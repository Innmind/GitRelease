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
        $process1 = $this->createMock(Process::class);
        $process2 = $this->createMock(Process::class);
        $process3 = $this->createMock(Process::class);
        $process4 = $this->createMock(Process::class);
        $processes
            ->expects($matcher = $this->exactly(4))
            ->method('execute')
            ->willReturnCallback(function($command) use ($matcher, $process1, $process2, $process3, $process4) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame("mkdir '-p' '/somewhere'", $command->toString()),
                    2 => $this->assertSame("git 'tag' '-s' '-a' '1.0.0' '-m' 'watev'", $command->toString()),
                    3 => $this->assertSame("git 'push'", $command->toString()),
                    4 => $this->assertSame("git 'push' '--tags'", $command->toString()),
                };

                if ($matcher->numberOfInvocations() !== 1) {
                    $this->assertSame('/somewhere', $command->workingDirectory()->match(
                        static fn($directory) => $directory->toString(),
                        static fn() => null,
                    ));
                }

                return match ($matcher->numberOfInvocations()) {
                    1 => $process1,
                    2 => $process2,
                    3 => $process3,
                    4 => $process4,
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
        $process3
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));
        $process4
            ->expects($this->once())
            ->method('wait')
            ->willReturn(Either::right(new SideEffect));

        $this->assertInstanceOf(
            SideEffect::class,
            $release(
                Repository::of($server, $path, new Clock(new UTC))->match(
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
