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
use Innmind\Immutable\{
    Attempt,
    Str,
};

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
     *
     * @return Attempt<Console>
     */
    public function __invoke(
        Console $console,
        callable $increase,
    ): Attempt {
        return $this->git->repository($console->workingDirectory())->match(
            fn($repository) => $this->release($console, $increase, $repository),
            static fn() => $console
                ->exit(1)
                ->error(Str::of("Not inside a repository\n")),
        );
    }

    /**
     * @param callable(Version): Version $increase
     *
     * @return Attempt<Console>
     */
    private function release(
        Console $console,
        callable $increase,
        Repository $repository,
    ): Attempt {
        $version = ($this->latestVersion)($repository);
        $newVersion = $increase($version);

        return $console
            ->output(Str::of("Current release: {$version->toString()}\n"))
            ->flatMap(static fn($console) => $console->output(
                Str::of("Next release: {$newVersion->toString()}\n"),
            ))
            ->flatMap(fn($console) => $this->doRelease(
                $console,
                $repository,
                $newVersion,
            ));
    }

    /**
     * @return Attempt<Console>
     */
    private function doRelease(
        Console $console,
        Repository $repository,
        Version $newVersion,
    ): Attempt {
        if ($console->options()->contains('message')) {
            $message = $console->options()->get('message');
        } else {
            /** @var Console $console */
            [$message, $console] = Question::of('message:')($console)->unwrap();
            $message = $message->unwrap()->toString();
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
     * @return Attempt<Console>
     */
    private function unsignedRelease(
        Console $console,
        string $message,
        Repository $repository,
        Version $newVersion,
    ): Attempt {
        $message = Message::maybe($message)->match(
            static fn($message) => $message,
            static fn() => null,
        );

        return ($this->unsignedRelease)($repository, $newVersion, $message)->eitherWay(
            static fn() => Attempt::result($console),
            static fn() => $console
                ->exit(1)
                ->error(Str::of("Release failed\n")),
        );
    }

    /**
     * @return Attempt<Console>
     */
    private function signedRelease(
        Console $console,
        string $message,
        Repository $repository,
        Version $newVersion,
    ): Attempt {
        return Message::maybe($message)
            ->attempt(static fn() => new \RuntimeException('Invalid message'))
            ->eitherWay(
                fn($message) => ($this->signedRelease)($repository, $newVersion, $message)->eitherWay(
                    static fn() => Attempt::result($console),
                    static fn() => $console
                        ->exit(1)
                        ->error(Str::of("Release failed\n")),
                ),
                static fn() => $console
                    ->exit(1)
                    ->error(Str::of("Invalid message\n")),
            );
    }
}
