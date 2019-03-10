<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Git\Git;
use Innmind\CLI\Commands;

function bootstrap(OperatingSystem $os): Commands
{
    $git = new Git($os->control());
    $release = new Release;
    $latestVersion = new LatestVersion;

    return new Commands(
        new Command\Major($git, $release, $latestVersion),
        new Command\Minor($git, $release, $latestVersion),
        new Command\Bugfix($git, $release, $latestVersion)
    );
}
