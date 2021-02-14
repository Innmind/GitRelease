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
    Server\Process\ExitCode,
};
use Innmind\Url\Path;
use Innmind\TimeContinuum\Earth\{
    Clock,
    Timezone\UTC,
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

        $this->assertNull($release(
            new Repository($server, $path, new Clock(new UTC)),
            new Version(1, 0, 0),
            new Message('watev')
        ));
    }
}
