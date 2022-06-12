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
        Message $message = null,
    ): void {
        $_ = $repository
            ->tags()
            ->add(
                Name::maybe($version->toString())->match(
                    static fn($name) => $name,
                    static fn() => throw new \RuntimeException,
                ),
                $message,
            )
            ->flatMap(static fn() => $repository->push())
            ->flatMap(static fn() => $repository->tags()->push())
            ->match(
                static fn() => null, // pass
                static fn() => throw new \RuntimeException,
            );
    }
}
