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
            ['id' => $story->getId(), 'status' => $story->getStatus()],
            Response::HTTP_ACCEPTED
        );
    }

    #[Route('/api/stories/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $story = $this->em->find(Story::class, $id);

        if ($story === null) {
            return new JsonResponse(['error' => 'story not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id'         => $story->getId(),
            'title'      => $story->getTitle(),
            'status'     => $story->getStatus(),
            'created_at' => $story->getCreatedAt()->format(DATE_ATOM),
        ]);
    }
}
