<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\StoryStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StoryStatusTest extends TestCase
{
    #[DataProvider('provideTransitions')]
    public function testCanTransitionTo(StoryStatus $from, StoryStatus $to, bool $expected): void
    {
        self::assertSame($expected, $from->canTransitionTo($to));
    }

    /** @return iterable<string, array{StoryStatus, StoryStatus, bool}> */
    public static function provideTransitions(): iterable
    {
        yield 'pending -> generating'    => [StoryStatus::Pending, StoryStatus::Generating, true];
        yield 'pending -> cancelled'     => [StoryStatus::Pending, StoryStatus::Cancelled, true];
        yield 'pending -> completed'     => [StoryStatus::Pending, StoryStatus::Completed, false];
        yield 'generating -> completed'  => [StoryStatus::Generating, StoryStatus::Completed, true];
        yield 'generating -> failed'     => [StoryStatus::Generating, StoryStatus::Failed, true];
        yield 'generating -> cancelled'  => [StoryStatus::Generating, StoryStatus::Cancelled, false];
        yield 'completed is terminal'    => [StoryStatus::Completed, StoryStatus::Cancelled, false];
        yield 'cancelled is terminal'    => [StoryStatus::Cancelled, StoryStatus::Cancelled, false];
    }
}
