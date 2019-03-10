<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\Git\{
    Repository,
    Message,
    Repository\Tag\Name,
};
use Innmind\Url\PathInterface;

final class Release
{
    public function __invoke(
        Repository $repository,
        Version $version,
        Message $message
    ): void {
        $repository->tags()->sign(
            new Name((string) $version),
            $message
        );
        $repository->push();
        $repository->tags()->push();
    }
}
