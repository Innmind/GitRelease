<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease;

use Innmind\GitRelease\{
    LatestVersion,
    Version,
    Exception\UnknownVersionFormat,
};
use Innmind\Git\Repository;
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\ExitCode,
    Server\Process\Output,
};
use Innmind\Url\Path;
use Innmind\TimeContinuum\Earth\{
    Clock,
    Timezone\UTC,
};
use PHPUnit\Framework\TestCase;

class LatestVersionTest extends TestCase
{
    public function testWhenNoTag()
    {
        $version = $this->fromOutput('');

        $this->assertInstanceOf(Version::class, $version);
        $this->assertSame('0.0.0', $version->toString());
    }

    public function testLatestVersion()
    {
        $version = $this->fromOutput("1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100\n1.10.0|||bar|||Mon, 18 Mar 2019 12:09:24 +0100\n1.2.0|||baz|||Sun, 17 Mar 2019 12:09:24 +0100");

        $this->assertInstanceOf(Version::class, $version);
        $this->assertSame('1.10.0', $version->toString());
    }

    public function testThrowWhenUnknownFormat()
    {
        $this->expectException(UnknownVersionFormat::class);
        $this->expectExceptionMessage('v1.0.0');

        $this->fromOutput('v1.0.0|||foo|||Sat, 16 Mar 2019 12:09:24 +0100');
    }

    private function fromOutput(string $data)
    {
        $latestVersion = new LatestVersion;
        $server = $this->createMock(Server::class);
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
            ->willReturn($data);
        $repository = new Repository(
            $server,
            Path::of('/somewhere'),
            new Clock(new UTC)
        );

        return $latestVersion($repository);
    }
}
