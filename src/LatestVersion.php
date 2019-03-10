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
use Composer\Semver\Semver;

final class LatestVersion
{
    public function __invoke(Repository $repository): Version
    {
        $tags = $repository->tags()->all();

        if ($tags->empty()) {
            return new Version(0, 0, 0);
        }

        $versions = $tags->reduce(
            [],
            static function(array $versions, Tag $tag): array {
                $versions[] = (string) $tag->name();

                return $versions;
            }
        );

        try {
            return Version::of(Semver::rsort($versions)[0]);
        } catch (DomainException $e) {
            throw new UnknownVersionFormat($e->getMessage());
        }
    }
}
