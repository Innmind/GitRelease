<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\GitRelease\Exception\DomainException;
use Innmind\Immutable\Str;

final class Version
{
    private $major;
    private $minor;
    private $bugfix;

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
            throw new DomainException((string) $version);
        }

        $parts = $version->split('.');

        return new self(
            (int) (string) $parts->get(0),
            (int) (string) $parts->get(1),
            (int) (string) $parts->get(2)
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

    public function __toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->bugfix}";
    }
}
