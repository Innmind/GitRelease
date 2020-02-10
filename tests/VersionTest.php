<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease;

use Innmind\GitRelease\{
    Version,
    Exception\DomainException,
};
use PHPUnit\Framework\TestCase;
use Eris\{
    Generator,
    TestTrait,
};

class VersionTest extends TestCase
{
    use TestTrait;

    public function testInterface()
    {
        $this
            ->forAll(
                Generator\nat(),
                Generator\nat(),
                Generator\nat()
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $this->assertSame(
                    "$major.$minor.$bugfix",
                    (new Version($major, $minor, $bugfix))->toString(),
                );
            });
    }

    public function testThrowWhenNegativeMajor()
    {
        $this
            ->forAll(
                Generator\neg(),
                Generator\nat(),
                Generator\nat()
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $this->expectException(DomainException::class);
                $this->expectExceptionMessage("$major.$minor.$bugfix");

                new Version($major, $minor, $bugfix);
            });
    }

    public function testThrowWhenNegativeMinor()
    {
        $this
            ->forAll(
                Generator\nat(),
                Generator\neg(),
                Generator\nat()
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $this->expectException(DomainException::class);
                $this->expectExceptionMessage("$major.$minor.$bugfix");

                new Version($major, $minor, $bugfix);
            });
    }

    public function testThrowWhenNegativeBugfix()
    {
        $this
            ->forAll(
                Generator\nat(),
                Generator\nat(),
                Generator\neg()
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $this->expectException(DomainException::class);
                $this->expectExceptionMessage("$major.$minor.$bugfix");

                new Version($major, $minor, $bugfix);
            });
    }

    public function testOf()
    {
        $this
            ->forAll(
                Generator\nat(),
                Generator\nat(),
                Generator\nat()
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $version = Version::of("$major.$minor.$bugfix");

                $this->assertInstanceOf(Version::class, $version);
                $this->assertSame("$major.$minor.$bugfix", $version->toString());
            });
    }

    public function testThrowWhenNotOfExpectedPattern()
    {
        $this
            ->forAll(Generator\string())
            ->then(function(string $pattern): void {
                $this->expectException(DomainException::class);
                $this->expectExceptionMessage($pattern);

                Version::of($pattern);
            });
    }

    public function testIncreaseMajor()
    {
        $this
            ->forAll(
                Generator\nat(),
                Generator\nat(),
                Generator\nat()
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $version = new Version($major, $minor, $bugfix);
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
                Generator\nat(),
                Generator\nat(),
                Generator\nat()
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $version = new Version($major, $minor, $bugfix);
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
                Generator\nat(),
                Generator\nat(),
                Generator\nat()
            )
            ->then(function(int $major, int $minor, int $bugfix): void {
                $version = new Version($major, $minor, $bugfix);
                $next = $version->increaseBugfix();

                $this->assertInstanceOf(Version::class, $next);
                ++$bugfix;
                $this->assertSame("$major.$minor.$bugfix", $next->toString());
            });
    }
}
