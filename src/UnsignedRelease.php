<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\Git\{
    Repository,
    Message,
    Repository\Tag\Name,
};
use Innmind\Immutable\{
    Attempt,
    SideEffect,
};

final class UnsignedRelease
{
    /**
     * @return Attempt<SideEffect>
     */
    public function __invoke(
        Repository $repository,
        Version $version,
        ?Message $message = null,
    ): Attempt {
        return Name::maybe($version->toString())
            ->attempt(static fn() => new \RuntimeException("Invalid version {$version->toString()}"))
            ->flatMap(static fn($name) => $repository->tags()->add($name, $message))
            ->flatMap(static fn() => $repository->push())
            ->flatMap(static fn() => $repository->tags()->push());
    }
}
