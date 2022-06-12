<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\Git\{
    Git,
    Message,
    Repository,
};
use Innmind\CLI\{
    Question\Question,
    Console,
};
use Innmind\Immutable\Str;

final class Release
{
    private Git $git;
    private SignedRelease $signedRelease;
    private UnsignedRelease $unsignedRelease;
    private LatestVersion $latestVersion;

    public function __construct(
        Git $git,
        SignedRelease $release,
        UnsignedRelease $unsignedRelease,
        LatestVersion $latestVersion,
    ) {
        $this->git = $git;
        $this->signedRelease = $release;
        $this->unsignedRelease = $unsignedRelease;
        $this->latestVersion = $latestVersion;
    }

    /**
     * @param callable(Version): Version $increase
     */
    public function __invoke(
        Console $console,
        callable $increase,
    ): Console {
        return $this->git->repository($console->workingDirectory())->match(
            fn($repository) => $this->release($console, $increase, $repository),
            static fn() => $console
                ->error(Str::of("Not inside a repository\n"))
                ->exit(1),
        );
    }

    /**
     * @param callable(Version): Version $increase
     */
    private function release(
        Console $console,
        callable $increase,
        Repository $repository,
    ): Console {
        $version = ($this->latestVersion)($repository);
        $newVersion = $increase($version);

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
