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

        $versions = $tags->sort(static function(Tag $a, Tag $b): bool {
            return $b->date()->aheadOf($a->date());
        });

        try {
            return Version::of(
                (string) $versions->first()->name()
            );
        } catch (DomainException $e) {
            throw new UnknownVersionFormat($e->getMessage());
        }
    }
}
