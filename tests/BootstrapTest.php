<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease;

use function Innmind\GitRelease\bootstrap;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\Commands;
use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testInvokation()
    {
        $this->assertInstanceOf(
            Commands::class,
            bootstrap($this->createMock(OperatingSystem::class)),
        );
    }
}
