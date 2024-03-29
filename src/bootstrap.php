<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Git\Git;
use Innmind\CLI\Commands;
use Innmind\Url\Path;

function bootstrap(OperatingSystem $os, Path $home): Commands
{
    $release = new Release(
        Git::of(
            $os->control(),
            $os->clock(),
            $home,
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
