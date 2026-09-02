<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateStoryRequest;
use App\Entity\Story;
use App\Entity\StoryStatus;
use App\Service\StoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StoryServiceTest extends TestCase
{
    public function testCreatePersistsAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Story::class));   // мок: проверяем взаимодействие
        $em->expects($this->once())->method('flush');

        $service = new StoryService($em);
        $story = $service->create(new CreateStoryRequest('Lighthouse', 'The keeper went silent'));

        self::assertSame(StoryStatus::Pending, $story->getStatus());
    }

    public function testCancelTransitionsAndFlushes(): void
    {
        $story = new Story('Lighthouse', 'The keeper went silent');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($story);          // стаб: подсовываем состояние
        $em->expects($this->once())->method('flush');

        $result = (new StoryService($em))->cancel(7);

        self::assertSame(StoryStatus::Cancelled, $result->getStatus());
    }

    public function testCancelUnknownStoryThrowsAndNeverFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects($this->never())->method('flush');    // важная половина проверки

        $this->expectException(NotFoundHttpException::class);

        (new StoryService($em))->cancel(999);
    }
}
