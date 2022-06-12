<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\GitRelease\Exception\DomainException;
use Innmind\Immutable\{
    Str,
    Maybe,
};

final class Version
{
    private int $major;
    private int $minor;
    private int $bugfix;

    public function __construct(int $major, int $minor, int $bugfix)
    {
        if (\min($major, $minor, $bugfix) < 0) {
            throw new DomainException("$major.$minor.$bugfix");
        }

        $this->major = $major;
        $this->minor = $minor;
        $this->bugfix = $bugfix;
    }

    public static function of(string $version): self
    {
        $version = Str::of($version);

        if (!$version->matches('~^\d+\.\d+\.\d+$~')) {
            throw new DomainException($version->toString());
        }

        $parts = $version->split('.');
        $major = $parts
            ->get(0)
            ->map(static fn($major) => (int) $major->toString());
        $minor = $parts
            ->get(1)
            ->map(static fn($minor) => (int) $minor->toString());
        $bugfix = $parts
            ->get(2)
            ->map(static fn($bugfix) => (int) $bugfix->toString());

        return Maybe::all($major, $minor, $bugfix)
            ->map(static fn(int $major, int $minor, int $bugfix) => new self(
                $major,
                $minor,
                $bugfix,
            ))
            ->match(
                static fn($self) => $self,
                static fn() => throw new DomainException($version->toString()),
            );
    }

    public function increaseMajor(): self
    {
        return new self(
            $this->major + 1,
            0,
            0,
        );
    }

    public function increaseMinor(): self
    {
        return new self(
            $this->major,
            $this->minor + 1,
            0,
        );
    }

    public function increaseBugfix(): self
    {
        return new self(
            $this->major,
            $this->minor,
            $this->bugfix + 1,
        );
    }

    public function toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->bugfix}";
    }
}
