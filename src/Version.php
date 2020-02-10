<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\GitRelease\Exception\DomainException;
use Innmind\Immutable\Str;

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

        return new self(
            (int) $parts->get(0)->toString(),
            (int) $parts->get(1)->toString(),
            (int) $parts->get(2)->toString(),
        );
    }

    public function increaseMajor(): self
    {
        return new self(
            $this->major + 1,
            0,
            0
        );
    }

    public function increaseMinor(): self
    {
        return new self(
            $this->major,
            $this->minor + 1,
            0
        );
    }

    public function increaseBugfix(): self
    {
        return new self(
            $this->major,
            $this->minor,
            $this->bugfix + 1
        );
    }

    public function toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->bugfix}";
    }
}
