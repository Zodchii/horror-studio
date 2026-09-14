<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateStoryRequest;
use App\Entity\Story;
use App\Entity\StoryStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\GenerateStoryMessage;

final class StoryService
{
    public function __construct(private readonly EntityManagerInterface $em
        ,private readonly MessageBusInterface $bus)
    {
    }

    public function create(CreateStoryRequest $request): Story
    {
        $story = new Story($request->title, $request->prompt);
        $this->em->persist($story);
        $this->em->flush();
        if($story->getId() !== null) {
            $this->bus->dispatch(new GenerateStoryMessage($story->getId()));
        }


        return $story;
    }

    public function getById(int $id): Story
    {
        return $this->em->find(Story::class, $id)
            ?? throw new NotFoundHttpException('story not found');
    }

    public function cancel(int $id): Story
    {
        $story = $this->getById($id);
        $story->transitionTo(StoryStatus::Cancelled);
        $this->em->flush();
        return $story;
    }
}
