<?php
declare(strict_types = 1);

namespace Innmind\GitRelease\Command;

use Innmind\GitRelease\{
    SignedRelease,
    LatestVersion,
    Exception\UnknownVersionFormat,
    UnsignedRelease,
};
use Innmind\Git\{
    Git,
    Message,
    Exception\DomainException,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
    Question\Question,
};
use Innmind\Immutable\Str;

final class Major implements Command
{
    private Git $git;
    private SignedRelease $signedRelease;
    private UnsignedRelease $unsignedRelease;
    private LatestVersion $latestVersion;

    public function __construct(
        Git $git,
        SignedRelease $signedRelease,
        UnsignedRelease $unsignedRelease,
        LatestVersion $latestVersion
    ) {
        $this->git = $git;
        $this->signedRelease = $signedRelease;
        $this->unsignedRelease = $unsignedRelease;
        $this->latestVersion = $latestVersion;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $repository = $this->git->repository($env->workingDirectory());

        try {
            $version = ($this->latestVersion)($repository);
            $newVersion = $version->increaseMajor();
        } catch (UnknownVersionFormat $e) {
            $env->error()->write(Str::of("Unsupported tag name format\n"));
            $env->exit(1);

            return;
        }

        $env->output()->write(Str::of("Current release: $version\n"));
        $env->output()->write(Str::of("Next release: $newVersion\n"));

        if ($options->contains('message')) {
            $message = $options->get('message');
        } else {
            $message = (new Question('message:'))($env->input(), $env->output());
        }

        $isSignedRelease = !$options->contains('no-sign');

        try {
            $message = new Message((string) $message);
        } catch (DomainException $e) {
            if ($isSignedRelease) {
                $env->error()->write(Str::of("Invalid message\n"));
                $env->exit(1);

                return;
            }

            $message = null;
        }

        if (!$isSignedRelease) {
            ($this->unsignedRelease)($repository, $newVersion, $message);

            return;
        }

        ($this->signedRelease)($repository, $newVersion, $message);
    }

    public function __toString(): string
    {
        return <<<USAGE
major --no-sign --message=

Create a new major tag and push it
USAGE;
    }
}
