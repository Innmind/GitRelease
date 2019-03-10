<?php
declare(strict_types = 1);

namespace Tests\Innmind\GitRelease;

use Innmind\GitRelease\{
    Message,
    Exception\DomainException,
};
use PHPUnit\Framework\TestCase;
use Eris\{
    Generator,
    TestTrait,
};

class MessageTest extends TestCase
{
    use TestTrait;

    public function testInterface()
    {
        $this
            ->forAll(Generator\string())
            ->when(static function(string $message): bool {
                return $message !== '';
            })
            ->then(function(string $message): void {
                $this->assertSame($message, (string) new Message($message));
            });
    }

    public function testThrowWhenEmptyMessage()
    {
        $this->expectException(DomainException::class);

        new Message('');
    }
}
