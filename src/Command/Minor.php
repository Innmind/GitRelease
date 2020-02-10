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
use Innmind\OperatingSystem\Sockets;
use Innmind\Immutable\Str;

final class Minor implements Command
{
    private Git $git;
    private SignedRelease $signedRelease;
    private UnsignedRelease $unsignedRelease;
    private LatestVersion $latestVersion;
    private Sockets $sockets;

    public function __construct(
        Git $git,
        SignedRelease $signedRelease,
        UnsignedRelease $unsignedRelease,
        LatestVersion $latestVersion,
        Sockets $sockets
    ) {
        $this->git = $git;
        $this->signedRelease = $signedRelease;
        $this->unsignedRelease = $unsignedRelease;
        $this->latestVersion = $latestVersion;
        $this->sockets = $sockets;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $repository = $this->git->repository($env->workingDirectory());

        try {
            $version = ($this->latestVersion)($repository);
            $newVersion = $version->increaseMinor();
        } catch (UnknownVersionFormat $e) {
            $env->error()->write(Str::of("Unsupported tag name format\n"));
            $env->exit(1);

            return;
        }

        $env->output()->write(Str::of("Current release: {$version->toString()}\n"));
        $env->output()->write(Str::of("Next release: {$newVersion->toString()}\n"));

        if ($options->contains('message')) {
            $message = $options->get('message');
        } else {
            $message = (new Question('message:'))($env, $this->sockets)->toString();
        }

        $isSignedRelease = !$options->contains('no-sign');

        try {
            $message = new Message($message);
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

        /** @psalm-suppress PossiblyNullArgument false positive as if the message is invalid we return in the catch above */
        ($this->signedRelease)($repository, $newVersion, $message);
    }

    public function toString(): string
    {
        return <<<USAGE
minor --no-sign --message=

Create a new minor tag and push it
USAGE;
    }
}
