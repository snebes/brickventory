<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\OrderEvent;
use App\Event\PurchaseOrderUpdatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PurchaseOrderUpdatedEvent::class)]
class PurchaseOrderUpdatedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(PurchaseOrderUpdatedEvent $event): void
    {
        $purchaseOrder = $event->getPurchaseOrder();
        $previousState = $event->getPreviousState();

        // Create order event in event store
        $orderEvent = new OrderEvent();
        $orderEvent->orderType = 'purchase_order';
        $orderEvent->orderId = $purchaseOrder->id;
        $orderEvent->eventType = 'updated';
        $orderEvent->previousState = $previousState ? json_encode($previousState) : null;
        $orderEvent->newState = json_encode($this->serializePurchaseOrder($purchaseOrder));
        $orderEvent->metadata = json_encode([
            'order_number' => $purchaseOrder->orderNumber,
            'reference' => $purchaseOrder->reference,
        ]);

        $this->entityManager->persist($orderEvent);
        $this->entityManager->flush();
    }

    private function serializePurchaseOrder($po): array
    {
        return [
            'id' => $po->id,
            'orderNumber' => $po->orderNumber,
            'orderDate' => $po->orderDate->format('Y-m-d H:i:s'),
            'status' => $po->status,
            'reference' => $po->reference,
            'notes' => $po->notes,
            'lines' => array_map(function ($line) {
                return [
                    'id' => $line->id ?? null,
                    'itemId' => $line->item->id,
                    'itemName' => $line->item->itemName,
                    'quantityOrdered' => $line->quantityOrdered,
                    'rate' => $line->rate,
                ];
            }, $po->lines->toArray()),
        ];
    }
}
