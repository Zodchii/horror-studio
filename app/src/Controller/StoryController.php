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
use App\Service\StoryService;

final class StoryController
{
    public function __construct(private readonly StoryService $stories)
    {
    }

    #[Route('/api/stories', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateStoryRequest $request): JsonResponse
    {
        $story = $this->stories->create($request);

        return new JsonResponse(
            ['id' => $story->getId(), 'status' => $story->getStatus()->value],
            Response::HTTP_ACCEPTED,
            ['Location' => '/api/stories/' . $story->getId()]
        );
    }

    #[Route('/api/stories/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $story = $this->stories->getById($id);

        return new JsonResponse([
            'id'         => $story->getId(),
            'title'      => $story->getTitle(),
            'status'     => $story->getStatus()->value,
            'content'    => $story->getContent(),
            'created_at' => $story->getCreatedAt()->format(DATE_ATOM),
        ]);
    }

    #[Route('/api/stories/{id}/cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        $story = $this->stories->cancel($id);

        return new JsonResponse(['id' => $story->getId(), 'status' => $story->getStatus()->value]);
    }
}
