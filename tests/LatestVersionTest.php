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

    public function testRegressionOnSortForPHP8()
    {
        $output = <<<OUTPUT
1.0.0|||first release|||Sun, 29 Sep 2019 16:52:37 +0200
2.0.0|||use generators to avoid loading whole sets|||Sat, 5 Oct 2019 12:55:12 +0200
2.1.0|||add url set|||Sat, 12 Oct 2019 13:58:43 +0200
2.2.0|||add colour set|||Sat, 12 Oct 2019 14:54:07 +0200
2.3.0|||add decorate set + Scenario::take and Scenario::filter|||Sat, 12 Oct 2019 16:48:16 +0200
2.4.0|||add sequence/stream/set/map sets|||Sun, 13 Oct 2019 11:09:48 +0200
2.5.0|||add either set|||Sun, 20 Oct 2019 12:42:02 +0200
2.6.0|||improve naming of named constructors|||Sun, 20 Oct 2019 13:55:31 +0200
2.7.0|||only take 100 combinations by default for any scenario|||Sat, 2 Nov 2019 14:01:51 +0100
2.8.0|||add email set|||Sun, 10 Nov 2019 10:58:22 +0100
2.8.1|||fix generation of invalid email|||Mon, 11 Nov 2019 11:16:48 +0100
2.9.0|||allow to specify a upper or lower bound for a point in time|||Sat, 30 Nov 2019 11:35:30 +0100
3.0.0|||make lib dependency free|||Sat, 30 Nov 2019 14:47:01 +0100
4.0.0|||support generating mutable values|||Sun, 5 Jan 2020 16:50:17 +0100
4.1.0|||add shrinking and Regex set|||Tue, 18 Feb 2020 20:03:15 +0100
4.1.1|||fix email set generating invalid data|||Sun, 23 Feb 2020 10:59:29 +0100
4.10.0|||improve sequence shrinking|||Thu, 18 Jun 2020 14:55:49 +0200
4.10.1|||fix partial sequences sets + allow to filter Either sets|||Sat, 27 Jun 2020 17:17:12 +0200
4.11.0|||add set that return any type|||Tue, 14 Jul 2020 14:46:09 +0200
4.12.0|||allow to return the last value returned by the test callback|||Thu, 23 Jul 2020 13:41:00 +0200
4.13.0|||allow to disable shrinking for the whole test suite|||Fri, 20 Nov 2020 09:20:53 +0100
4.14.0|||allow php 8 + make reverse-regex dependency optional|||Sun, 10 Jan 2021 17:12:54 +0100
4.15.0|||support Set\Email for php 8|||Sun, 10 Jan 2021 18:18:44 +0100
4.15.1|||fix composite very slow shrinking|||Sat, 23 Jan 2021 14:40:23 +0100
4.16.0|||deprecate the use of Set\Regex + allow to shrink chars|||Sun, 31 Jan 2021 11:42:44 +0100
4.17.0|||add new string sets|||Mon, 1 Mar 2021 11:10:41 +0100
4.2.0|||diversify combinations + add env var to configure number of iterations + fix email set|||Sat, 29 Feb 2020 17:45:04 +0100
4.2.1|||fix shrinked values not respecting initial set bounds|||Tue, 3 Mar 2020 18:34:01 +0100
4.2.2|||fix removing vendor recursion stack trace|||Sat, 7 Mar 2020 15:46:38 +0100
4.2.3|||fix type declarations|||Sun, 8 Mar 2020 12:43:31 +0100
4.2.4|||fix not spreading the list of args of the scenario when filtering|||Sat, 4 Apr 2020 17:25:29 +0200
4.2.5|||fix sets yielding null values|||Sat, 4 Apr 2020 20:25:04 +0200
4.3.0|||shrink thrown exceptions|||Sun, 5 Apr 2020 19:13:45 +0200
4.3.1|||fix shrinking memory footprint + fix phpunit printer|||Sat, 18 Apr 2020 16:45:04 +0200
4.4.0|||add sequence set + stateful testing|||Sat, 25 Apr 2020 16:38:32 +0200
4.4.1|||fix sequence implementation|||Sat, 25 Apr 2020 18:25:54 +0200
4.5.0|||extract randomness source out of the sets|||Sun, 26 Apr 2020 15:23:10 +0200
4.6.0|||expose seeder to help seed properties objects|||Sat, 2 May 2020 13:36:23 +0200
4.7.0|||add property parameterization|||Sun, 3 May 2020 17:26:36 +0200
4.7.1|||fix shrinking not finding smallest values possible|||Fri, 8 May 2020 17:56:04 +0200
4.8.0|||add unicode set|||Sun, 17 May 2020 13:54:34 +0200
4.9.0|||support phpunit 9|||Thu, 21 May 2020 11:11:07 +0200
OUTPUT;

        $version = $this->fromOutput($output);

        $this->assertInstanceOf(Version::class, $version);
        $this->assertSame('4.17.0', $version->toString());
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
            new Clock(new UTC),
        );

        return $latestVersion($repository);
    }
}
