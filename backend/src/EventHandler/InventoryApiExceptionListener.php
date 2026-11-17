<?php

declare(strict_types=1);
namespace App\EventHandler;

use Doctrine\DBAL\Exception\{UniqueConstraintViolationException, ForeignKeyConstraintViolationException};
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[AsEventListener]
final class InventoryApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/v1/')) return;
        $error = $event->getThrowable();
        if ($error instanceof \Symfony\Component\Serializer\Exception\ExtraAttributesException) {
            $event->setResponse(new JsonResponse(['message' => $error->getMessage()], 422));
        } elseif ($error instanceof UniqueConstraintViolationException || $error instanceof ForeignKeyConstraintViolationException) {
            $event->setResponse(new JsonResponse(['message' => 'Duplicate value or record referenced by another resource.'], 409));
        } elseif ($error instanceof HttpExceptionInterface) {
            $event->setResponse(new JsonResponse(['message' => $error->getMessage()], $error->getStatusCode(), $error->getHeaders()));
        }
    }
}
