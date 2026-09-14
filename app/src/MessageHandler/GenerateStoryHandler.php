<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Story;
use App\Entity\StoryStatus;
use App\Message\GenerateStoryMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateStoryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateStoryMessage $message): void
    {
        $story = $this->em->find(Story::class, $message->storyId);

        if ($story === null) {
            $this->logger->info('skip: story not found', ['storyId' => $message->storyId]);

            return;
        }

        $status = $story->getStatus();

        if ($status !== StoryStatus::Pending && $status !== StoryStatus::Generating) {
            $this->logger->info('skip generation', [
                'storyId' => $story->getId(),
                'status'  => $status->value,
            ]);

            return; // отменена или уже завершена — от этого guard и защищает
        }

        if ($status === StoryStatus::Pending) {
            $story->transitionTo(StoryStatus::Generating);
            $this->em->flush();
        }
// если статус уже Generating — это повторная доставка, просто продолжаем

        sleep(5); // здесь думает «LLM» — окно для убийства воркера

        if (str_contains($story->getPrompt(), 'poison')) {
            throw new \RuntimeException('LLM rejected the prompt'); // ядовитое — умрёт все ретраи
        }

        if (random_int(1, 100) <= 15) {
            throw new \RuntimeException('LLM flaked, temporary'); // мигающий сбой — ретрай спасёт
        }

        $story->markCompleted(sprintf(
            "== %s ==\n\nChapter 1.\n%s... and the door creaked open.",
            $story->getTitle(),
            $story->getPrompt()
        ));
        $this->em->flush();

        $this->logger->info('story generated', ['storyId' => $story->getId()]);
    }
}
