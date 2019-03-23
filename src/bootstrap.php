<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Git\Git;
use Innmind\CLI\Commands;

function bootstrap(OperatingSystem $os): Commands
{
    $git = new Git($os->control());
    $signedRelease = new SignedRelease;
    $unsignedRelease = new UnsignedRelease;
    $latestVersion = new LatestVersion;

    return new Commands(
        new Command\Major($git, $signedRelease, $unsignedRelease, $latestVersion),
        new Command\Minor($git, $signedRelease, $unsignedRelease, $latestVersion),
        new Command\Bugfix($git, $signedRelease, $unsignedRelease, $latestVersion)
    );
}
