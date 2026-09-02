<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Story;
use App\Entity\StoryStatus;
use PHPUnit\Framework\TestCase;

final class StoryTest extends TestCase
{
    public function testLegalTransitionChangesStatus(): void
    {
        $story = new Story('Lighthouse', 'The keeper went silent');   // Arrange

        $story->transitionTo(StoryStatus::Generating);                // Act

        self::assertSame(StoryStatus::Generating, $story->getStatus()); // Assert
    }

    public function testIllegalTransitionThrowsAndKeepsStatus(): void
    {
        $story = new Story('Lighthouse', 'The keeper went silent');
        $story->transitionTo(StoryStatus::Cancelled);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Illegal transition');

        $story->transitionTo(StoryStatus::Cancelled);
    }
}
