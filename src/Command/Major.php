<?php
declare(strict_types = 1);

namespace Innmind\GitRelease\Command;

use Innmind\GitRelease\Release;
use Innmind\CLI\{
    Command,
    Console,
};

final class Major implements Command
{
    private Release $release;

    public function __construct(Release $release)
    {
        $this->release = $release;
    }

    #[\Override]
    public function __invoke(Console $console): Console
    {
        return ($this->release)(
            $console,
            static fn($version) => $version->increaseMajor(),
        );
    }

    /**
     * @psalm-pure
     */
    #[\Override]
    public function usage(): string
    {
        return <<<USAGE
            major --no-sign --message=

            Create a new major tag and push it
            USAGE;
    }
}
