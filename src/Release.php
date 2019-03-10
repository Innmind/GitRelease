<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\Git\{
    Git,
    Message,
    Repository\Tag\Name,
};
use Innmind\Url\PathInterface;

final class Release
{
    private $git;

    public function __construct(Git $git)
    {
        $this->git = $git;
    }

    public function __invoke(
        PathInterface $repository,
        Version $version,
        Message $message
    ): void {
        $repository = $this->git->repository($repository);
        $repository->tags()->add(
            new Name((string) $version),
            $message
        );
        $repository->push();
        $repository->tags()->push();
    }
}
