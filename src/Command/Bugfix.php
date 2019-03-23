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

final class Bugfix implements Command
{
    private $git;
    private $signedRelease;
    private $unsignedRelease;
    private $latestVersion;

    public function __construct(
        Git $git,
        SignedRelease $release,
        UnsignedRelease $unsignedRelease,
        LatestVersion $latestVersion
    ) {
        $this->git = $git;
        $this->signedRelease = $release;
        $this->unsignedRelease = $unsignedRelease;
        $this->latestVersion = $latestVersion;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $repository = $this->git->repository($env->workingDirectory());

        try {
            $version = ($this->latestVersion)($repository);
            $newVersion = $version->increaseBugfix();
        } catch (UnknownVersionFormat $e) {
            $env->error()->write(Str::of("Unsupported tag name format\n"));
            $env->exit(1);

            return;
        }

        $env->output()->write(Str::of("Current release: $version\n"));
        $env->output()->write(Str::of("Next release: $newVersion\n"));
        $message = (new Question('message:'))($env->input(), $env->output());

        try {
            $message = new Message((string) $message);
        } catch (DomainException $e) {
            $env->error()->write(Str::of("Invalid message\n"));
            $env->exit(1);

            return;
        }

        if ($options->contains('no-sign')) {
            ($this->unsignedRelease)($repository, $newVersion, $message);

            return;
        }

        ($this->signedRelease)($repository, $newVersion, $message);
    }

    public function __toString(): string
    {
        return <<<USAGE
bugfix --no-sign

Create a new bugfix tag and push it
USAGE;
    }
}
