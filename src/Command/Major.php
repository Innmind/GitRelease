<?php
declare(strict_types = 1);

namespace Innmind\GitRelease\Command;

use Innmind\GitRelease\Release;
use Innmind\CLI\{
    Command,
    Command\Name,
    Command\Usage,
    Console,
};
use Innmind\Immutable\Attempt;

#[Name('major', 'Create a new major tag and push it')]
final class Major implements Command
{
    private Release $release;

    public function __construct(Release $release)
    {
        $this->release = $release;
    }

    #[\Override]
    public function __invoke(Console $console): Attempt
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
    public function usage(): Usage
    {
        return Usage::for(self::class)
            ->flag('no-sign')
            ->option('message');
    }
}
