<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Git\Git;
use Innmind\CLI\Commands;

function bootstrap(OperatingSystem $os): Commands
{
    $release = new Release(
        Git::of(
            $os->control(),
            $os->clock(),
        ),
        new SignedRelease,
        new UnsignedRelease,
        new LatestVersion,
    );

    return Commands::of(
        new Command\Major($release),
        new Command\Minor($release),
        new Command\Bugfix($release),
    );
}
