<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\Git\{
    Repository,
    Message,
    Repository\Tag\Name,
};
use Innmind\Immutable\{
    Maybe,
    SideEffect,
};

final class SignedRelease
{
    /**
     * @return Maybe<SideEffect>
     */
    public function __invoke(
        Repository $repository,
        Version $version,
        Message $message,
    ): Maybe {
        return Name::maybe($version->toString())
            ->flatMap(static fn($name) => $repository->tags()->sign($name, $message))
            ->flatMap(static fn() => $repository->push())
            ->flatMap(static fn() => $repository->tags()->push());
    }
}
