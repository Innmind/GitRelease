<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\GitRelease\Exception\{
    DomainException,
    UnknownVersionFormat,
};
use Innmind\Git\{
    Repository,
    Repository\Tag,
};

final class LatestVersion
{
    public function __invoke(Repository $repository): Version
    {
        $tags = $repository->tags()->all();

        if ($tags->empty()) {
            return new Version(0, 0, 0);
        }

        $versions = $tags->sort(static function(Tag $a, Tag $b): int {
            return $b->date()->aheadOf($a->date()) ? 1 : -1;
        });

        try {
            return Version::of(
                $versions->first()->name()->toString(),
            );
        } catch (DomainException $e) {
            throw new UnknownVersionFormat($e->getMessage());
        }
    }
}
