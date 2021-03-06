<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\Git\{
    Repository,
    Message,
    Repository\Tag\Name,
};

final class SignedRelease
{
    public function __invoke(
        Repository $repository,
        Version $version,
        Message $message
    ): void {
        $repository->tags()->sign(
            new Name($version->toString()),
            $message,
        );
        $repository->push();
        $repository->tags()->push();
    }
}
