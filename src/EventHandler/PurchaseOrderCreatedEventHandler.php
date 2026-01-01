<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Event\PurchaseOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PurchaseOrderCreatedEvent::class)]
class PurchaseOrderCreatedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(PurchaseOrderCreatedEvent $event): void
    {
        $purchaseOrder = $event->getPurchaseOrder();

        // Update inventory for each line item using event sourcing
        foreach ($purchaseOrder->lines as $line) {
            $item = $line->item;
            
            // Create event in event store
            $itemEvent = new ItemEvent();
            $itemEvent->item = $item;
            $itemEvent->eventType = 'purchase_order_created';
            $itemEvent->quantityChange = $line->quantityOrdered;
            $itemEvent->referenceType = 'purchase_order';
            $itemEvent->referenceId = $purchaseOrder->id;
            $itemEvent->metadata = json_encode([
                'order_number' => $purchaseOrder->orderNumber,
                'reference' => $purchaseOrder->reference,
            ]);
            
            $this->entityManager->persist($itemEvent);
            
            // Update quantityOnOrder when a purchase order is created
            $item->quantityOnOrder += $line->quantityOrdered;
            
            // Note: quantityAvailable is NOT updated here because items are not yet received
            // quantityAvailable will be updated when items are actually received
            
            $this->entityManager->persist($item);
        }

        $this->entityManager->flush();
    }
}
