<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Story;
use App\Entity\StoryStatus;
use App\Message\GenerateStoryMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class StoryGenerationFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array<class-string, string> */
    public static function getSubscribedEvents(): array
    {
        return [WorkerMessageFailedEvent::class => 'onFailed'];
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return; // ещё поборемся
        }

        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof GenerateStoryMessage) {
            return;
        }

        $story = $this->em->find(Story::class, $message->storyId);

        if ($story?->getStatus() === StoryStatus::Generating) {
            $story->transitionTo(StoryStatus::Failed);
            $this->em->flush();
        }
    }
}
