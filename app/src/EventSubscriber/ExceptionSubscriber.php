<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    /** @return array<string, mixed> */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 0]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        [$status, $payload] = match (true) {
            $e instanceof \DomainException
            => [Response::HTTP_CONFLICT, ['error' => $e->getMessage()]],
            $e instanceof HttpExceptionInterface
            => [$e->getStatusCode(), $this->httpPayload($e)],
            default
            => [Response::HTTP_INTERNAL_SERVER_ERROR, [
                'error' => $this->environment === 'dev' ? $e->getMessage() : 'internal server error',
            ]],
        };

        $response = new JsonResponse($payload, $status);

        if ($e instanceof HttpExceptionInterface) {
            $response->headers->add($e->getHeaders());
        }

        $event->setResponse($response);
    }

    /** @return array<string, mixed> */
    private function httpPayload(HttpExceptionInterface $e): array
    {
        $previous = $e->getPrevious();

        if ($previous instanceof ValidationFailedException) {
            $violations = [];
            foreach ($previous->getViolations() as $violation) {
                $violations[] = [
                    'field'   => $violation->getPropertyPath(),
                    'message' => (string) $violation->getMessage(),
                ];
            }

            return ['error' => 'validation failed', 'violations' => $violations];
        }

        $message = $e->getMessage();

        return ['error' => $message !== '' ? $message : (Response::$statusTexts[$e->getStatusCode()] ?? 'error')];
    }
}
