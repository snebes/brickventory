<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\OrderEvent;
use App\Event\PurchaseOrderDeletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PurchaseOrderDeletedEvent::class)]
class PurchaseOrderDeletedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(PurchaseOrderDeletedEvent $event): void
    {
        // Create order event in event store
        $orderEvent = new OrderEvent();
        $orderEvent->orderType = 'purchase_order';
        $orderEvent->orderId = $event->getOrderId();
        $orderEvent->eventType = 'deleted';
        $orderEvent->previousState = json_encode($event->getOrderState());
        $orderEvent->newState = null;
        $orderEvent->metadata = json_encode([
            'order_number' => $event->getOrderState()['orderNumber'] ?? null,
        ]);

        $this->entityManager->persist($orderEvent);
        $this->entityManager->flush();
    }
}
