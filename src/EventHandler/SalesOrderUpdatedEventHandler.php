<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\OrderEvent;
use App\Event\SalesOrderUpdatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: SalesOrderUpdatedEvent::class)]
class SalesOrderUpdatedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(SalesOrderUpdatedEvent $event): void
    {
        $salesOrder = $event->getSalesOrder();
        $previousState = $event->getPreviousState();

        // Create order event in event store
        $orderEvent = new OrderEvent();
        $orderEvent->orderType = 'sales_order';
        $orderEvent->orderId = $salesOrder->id;
        $orderEvent->eventType = 'updated';
        $orderEvent->previousState = $previousState ? json_encode($previousState) : null;
        $orderEvent->newState = json_encode($this->serializeSalesOrder($salesOrder));
        $orderEvent->metadata = json_encode([
            'order_number' => $salesOrder->orderNumber,
        ]);

        $this->entityManager->persist($orderEvent);
        $this->entityManager->flush();
    }

    private function serializeSalesOrder($so): array
    {
        return [
            'id' => $so->id,
            'orderNumber' => $so->orderNumber,
            'orderDate' => $so->orderDate->format('Y-m-d H:i:s'),
            'status' => $so->status,
            'notes' => $so->notes,
            'lines' => array_map(function ($line) {
                return [
                    'id' => $line->id ?? null,
                    'itemId' => $line->item->id,
                    'itemName' => $line->item->itemName,
                    'quantityOrdered' => $line->quantityOrdered,
                ];
            }, $so->lines->toArray()),
        ];
    }
}
