<?php
declare(strict_types = 1);

namespace Innmind\GitRelease\Command;

use Innmind\GitRelease\Release;
use Innmind\CLI\{
    Command,
    Console,
};

final class Minor implements Command
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
            static fn($version) => $version->increaseMinor(),
        );
    }

    /**
     * @psalm-pure
     */
    public function usage(): string
    {
        return <<<USAGE
            minor --no-sign --message=

            Create a new minor tag and push it
            USAGE;
    }
}
