<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\Git\{
    Repository,
    Message,
    Repository\Tag\Name,
};

final class UnsignedRelease
{
    public function __invoke(
        Repository $repository,
        Version $version,
        Message $message
    ): void {
        $repository->tags()->add(
            new Name((string) $version),
            $message
        );
        $repository->push();
        $repository->tags()->push();
    }
}
