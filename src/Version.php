<?php
declare(strict_types = 1);

namespace Innmind\GitRelease;

use Innmind\Immutable\{
    Str,
    Maybe,
};

final class Version
{
    private int $major;
    private int $minor;
    private int $bugfix;

    private function __construct(int $major, int $minor, int $bugfix)
    {
        $this->major = $major;
        $this->minor = $minor;
        $this->bugfix = $bugfix;
    }

    public static function zero(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * @return Maybe<self>
     */
    public static function of(string $version): Maybe
    {
        $parts = Str::of($version)->capture('~^(?<major>\d+)\.(?<minor>\d+)\.(?<bugfix>\d+)$~');
        $major = $parts
            ->get('major')
            ->map(static fn($major) => (int) $major->toString())
            ->filter(static fn($major) => $major >= 0);
        $minor = $parts
            ->get('minor')
            ->map(static fn($minor) => (int) $minor->toString())
            ->filter(static fn($minor) => $minor >= 0);
        $bugfix = $parts
            ->get('bugfix')
            ->map(static fn($bugfix) => (int) $bugfix->toString())
            ->filter(static fn($bugfix) => $bugfix >= 0);

        return Maybe::all($major, $minor, $bugfix)
            ->map(static fn(int $major, int $minor, int $bugfix) => new self(
                $major,
                $minor,
                $bugfix,
            ));
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
