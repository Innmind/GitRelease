<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease;

use function Innmind\GitRelease\bootstrap;
use Innmind\OperatingSystem\Factory;
use Innmind\CLI\Commands;
use Innmind\Url\Path;
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testInvokation()
    {
        $this->assertInstanceOf(
            Commands::class,
            bootstrap(Factory::build(), Path::of('/')),
        );
    }
}
