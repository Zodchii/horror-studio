<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateStoryRequest;
use App\Entity\Story;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\StoryStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StoryController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/stories', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateStoryRequest $request): JsonResponse
    {
        $story = new Story($request->title, $request->prompt);
        $this->em->persist($story);
        $this->em->flush();

        return new JsonResponse(
            ['id' => $story->getId(), 'status' => $story->getStatus()->value],
            Response::HTTP_ACCEPTED,
            ['Location' => '/api/stories/' . $story->getId()]
        );
    }

    #[Route('/api/stories/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
//        $story = $this->em->find(Story::class, $id);
        $story = $this->em->find(Story::class, $id)
            ?? throw new NotFoundHttpException('story not found');
//        if ($story === null) {
//            return new JsonResponse(['error' => 'story not found'], Response::HTTP_NOT_FOUND);
//        }

        return new JsonResponse([
            'id'         => $story->getId(),
            'title'      => $story->getTitle(),
            'status'     => $story->getStatus()->value,
            'created_at' => $story->getCreatedAt()->format(DATE_ATOM),
        ]);
    }
    #[Route('/api/stories/{id}/cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        $story = $this->em->find(Story::class, $id)
                ?? throw new NotFoundHttpException('story not found');

//        if ($story === null) {
//            return new JsonResponse(['error' => 'story not found'], Response::HTTP_NOT_FOUND);
//        }

//        try {
            $story->transitionTo(StoryStatus::Cancelled);
//        } catch (\DomainException $e) {
//            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
//        }

        $this->em->flush();

        return new JsonResponse(['id' => $story->getId(), 'status' => $story->getStatus()->value]);
    }
}
