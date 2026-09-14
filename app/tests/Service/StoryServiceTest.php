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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;
use App\Message\GenerateStoryMessage;

final class StoryServiceTest extends TestCase
{
    public function testCreatePersistsAndFlushes(): void
    {
        $persistedStory = null;

        $em = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedStory): void {
                self::assertInstanceOf(Story::class, $entity);
                $persistedStory = $entity;
            });

        $em->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () use (&$persistedStory): void {
                $property = new \ReflectionProperty(Story::class, 'id');
                $property->setValue($persistedStory, 123);
            });

        $bus = $this->createMock(MessageBusInterface::class);

        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                fn (GenerateStoryMessage $message) => $message->storyId === 123
            ))
            ->willReturnCallback(
                fn (object $message) => new Envelope($message)
            );

        $service = new StoryService($em, $bus);

        $story = $service->create(
            new CreateStoryRequest(
                'Lighthouse',
                'The keeper went silent'
            )
        );

        self::assertSame(StoryStatus::Pending, $story->getStatus());
        self::assertSame(123, $story->getId());
    }

    public function testCancelTransitionsAndFlushes(): void
    {
        $story = new Story('Lighthouse', 'The keeper went silent');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($story);          // стаб: подсовываем состояние
        $em->expects($this->once())->method('flush');

        $bus = $this->createStub(MessageBusInterface::class);
        $result = (new StoryService($em,$bus))->cancel(7);

        self::assertSame(StoryStatus::Cancelled, $result->getStatus());
    }

    public function testCancelUnknownStoryThrowsAndNeverFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects($this->never())->method('flush');    // важная половина проверки

        $bus = $this->createStub(MessageBusInterface::class);
        $this->expectException(NotFoundHttpException::class);

        (new StoryService($em,$bus))->cancel(999);
    }
}
