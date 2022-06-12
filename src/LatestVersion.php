<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\Git\Repository;

final class LatestVersion
{
    public function __invoke(Repository $repository): Version
    {
        return $repository
            ->tags()
            ->all()
            ->sort(static fn($a, $b) => match ($b->date()->aheadOf($a->date())) {
                true => 1,
                false => -1,
            })
            ->first()
            ->map(static fn($version) => $version->name()->toString())
            ->match(
                static fn($version) => Version::of($version),
                static fn() => new Version(0, 0, 0),
            );
    }
}
