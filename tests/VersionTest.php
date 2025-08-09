<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease;

use Innmind\GitRelease\Version;
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    PHPUnit\Framework\TestCase,
    Set,
};

class VersionTest extends TestCase
{
    use BlackBox;

    public function testInterface()
    {
        $this
            ->forAll(
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $this->assertSame(
                    "$major.$minor.$bugfix",
                    Version::of("$major.$minor.$bugfix")->match(
                        static fn($version) => $version->toString(),
                        static fn() => null,
                    ),
                );
            });
    }

    public function testReturnNothingWhenNegativeMajor()
    {
        $this
            ->forAll(
                Set::integers()->below(-1),
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $this->assertNull(Version::of("$major.$minor.$bugfix")->match(
                    static fn($version) => $version,
                    static fn() => null,
                ));
            });
    }

    public function testReturnNothingWhenNegativeMinor()
    {
        $this
            ->forAll(
                Set::integers()->naturalNumbers(),
                Set::integers()->below(-1),
                Set::integers()->naturalNumbers(),
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $this->assertNull(Version::of("$major.$minor.$bugfix")->match(
                    static fn($version) => $version,
                    static fn() => null,
                ));
            });
    }

    public function testReturnNothingWhenNegativeBugfix()
    {
        $this
            ->forAll(
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
                Set::integers()->below(-1),
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $this->assertNull(Version::of("$major.$minor.$bugfix")->match(
                    static fn($version) => $version,
                    static fn() => null,
                ));
            });
    }

    public function testReturnNothingWhenNotOfExpectedPattern()
    {
        $this
            ->forAll(Set::strings())
            ->then(function(string $pattern): void {
                $this->assertNull(Version::of($pattern)->match(
                    static fn($version) => $version,
                    static fn() => null,
                ));
            });
    }

    public function testIncreaseMajor()
    {
        $this
            ->forAll(
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $version = Version::of("$major.$minor.$bugfix")->match(
                    static fn($version) => $version,
                    static fn() => null,
                );
                $next = $version->increaseMajor();

                $this->assertInstanceOf(Version::class, $next);
                ++$major;
                $this->assertSame("$major.0.0", $next->toString());
            });
    }

    public function testIncreaseMinor()
    {
        $this
            ->forAll(
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $version = Version::of("$major.$minor.$bugfix")->match(
                    static fn($version) => $version,
                    static fn() => null,
                );
                $next = $version->increaseMinor();

                $this->assertInstanceOf(Version::class, $next);
                ++$minor;
                $this->assertSame("$major.$minor.0", $next->toString());
            });
    }

    public function testIncreaseBugfix()
    {
        $this
            ->forAll(
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
                Set::integers()->naturalNumbers(),
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $version = Version::of("$major.$minor.$bugfix")->match(
                    static fn($version) => $version,
                    static fn() => null,
                );
                $next = $version->increaseBugfix();

                $this->assertInstanceOf(Version::class, $next);
                ++$bugfix;
                $this->assertSame("$major.$minor.$bugfix", $next->toString());
            });
    }
}
