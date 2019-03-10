<?php
declare(strict_types = 1);

namespace Innmind\GitRelease\Command;

use Innmind\GitRelease\{
    Release,
    LatestVersion,
    Exception\UnknownVersionFormat,
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
    private $release;
    private $latestVersion;

    public function __construct(Git $git, Release $release, LatestVersion $latestVersion)
    {
        $this->git = $git;
        $this->release = $release;
        $this->latestVersion = $latestVersion;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $repository = $this->git->repository($env->workingDirectory());

        try {
            $version = ($this->latestVersion)($repository)->increaseBugfix();
        } catch (UnknownVersionFormat $e) {
            $env->error()->write(Str::of("Unsupported tag name format\n"));
            $env->exit(1);

            return;
        }

        $env->output()->write(Str::of("$version\n"));
        $message = (new Question('message:'))($env->input(), $env->output());

        try {
            $message = new Message((string) $message);
        } catch (DomainException $e) {
            $env->error()->write(Str::of("Invalid message\n"));
            $env->exit(1);

            return;
        }

        ($this->release)($repository, $version, $message);
    }

    public function __toString(): string
    {
        return <<<USAGE
bugfix

Create a new bugfix tag and push it
USAGE;
    }
}
