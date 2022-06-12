<?php
declare(strict_types = 1);

namespace Innmind\GitRelease\Command;

use Innmind\GitRelease\{
    SignedRelease,
    LatestVersion,
    UnsignedRelease,
    Version,
};
use Innmind\Git\{
    Git,
    Message,
    Repository,
};
use Innmind\CLI\{
    Command,
    Question\Question,
    Console,
};
use Innmind\Immutable\Str;

final class Minor implements Command
{
    private Git $git;
    private SignedRelease $signedRelease;
    private UnsignedRelease $unsignedRelease;
    private LatestVersion $latestVersion;

    public function __construct(
        Git $git,
        SignedRelease $signedRelease,
        UnsignedRelease $unsignedRelease,
        LatestVersion $latestVersion,
    ) {
        $this->git = $git;
        $this->signedRelease = $signedRelease;
        $this->unsignedRelease = $unsignedRelease;
        $this->latestVersion = $latestVersion;
    }

    public function __invoke(Console $console): Console
    {
        $repository = $this->git->repository($console->workingDirectory())->match(
            static fn($repository) => $repository,
            static fn() => throw new \RuntimeException,
        );

        $version = ($this->latestVersion)($repository);
        $newVersion = $version->increaseMinor();

        $console = $console
            ->output(Str::of("Current release: {$version->toString()}\n"))
            ->output(Str::of("Next release: {$newVersion->toString()}\n"));

        if ($console->options()->contains('message')) {
            $message = $console->options()->get('message');
        } else {
            [$message, $console] = (new Question('message:'))($console);
            $message = $message->match(
                static fn($message) => $message->toString(),
                static fn() => throw new \RuntimeException,
            );
        }

        return $console
            ->options()
            ->maybe('no-sign')
            ->match(
                fn() => $this->unsignedRelease($console, $message, $repository, $newVersion),
                fn() => $this->signedRelease($console, $message, $repository, $newVersion),
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

    private function unsignedRelease(
        Console $console,
        string $message,
        Repository $repository,
        Version $newVersion,
    ): Console {
        $message = Message::maybe($message)->match(
            static fn($message) => $message,
            static fn() => null,
        );

        return ($this->unsignedRelease)($repository, $newVersion, $message)->match(
            static fn() => $console,
            static fn() => $console
                ->error(Str::of("Release failed\n"))
                ->exit(1),
        );
    }

    private function signedRelease(
        Console $console,
        string $message,
        Repository $repository,
        Version $newVersion,
    ): Console {
        return Message::maybe($message)->match(
            fn($message) => ($this->signedRelease)($repository, $newVersion, $message)->match(
                static fn() => $console,
                static fn() => $console
                    ->error(Str::of("Release failed\n"))
                    ->exit(1),
            ),
            static fn() => $console
                ->error(Str::of("Invalid message\n"))
                ->exit(1),
        );
    }
}
