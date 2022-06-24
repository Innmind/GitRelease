<?php
declare(strict_types = 1);

namespace Innmind\GitRelease\Command;

use Innmind\GitRelease\Release;
use Innmind\CLI\{
    Command,
    Console,
};

final class Bugfix implements Command
{
    private Release $release;

    public function __construct(Release $release)
    {
        $this->release = $release;
    }

    public function __invoke(Console $console): Console
    {
        return ($this->release)(
            $console,
            static fn($version) => $version->increaseBugfix(),
        );
    }

    /**
     * @psalm-pure
     */
    public function usage(): string
    {
        return <<<USAGE
            bugfix --no-sign --message=

            Create a new bugfix tag and push it
            USAGE;
    }
}
