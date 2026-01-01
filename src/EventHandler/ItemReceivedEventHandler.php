<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Event\ItemReceivedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event handler that records item receipt events in the event store
 */
#[AsEventListener(event: ItemReceivedEvent::class)]
class ItemReceivedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(ItemReceivedEvent $event): void
    {
        $item = $event->getItem();
        $quantity = $event->getQuantity();
        $purchaseOrder = $event->getPurchaseOrder();

        // Create event in event store
        $itemEvent = new ItemEvent();
        $itemEvent->item = $item;
        $itemEvent->eventType = 'item_received';
        $itemEvent->quantityChange = $quantity;
        $itemEvent->referenceType = 'purchase_order';
        $itemEvent->referenceId = $purchaseOrder->id;
        $itemEvent->metadata = json_encode([
            'order_number' => $purchaseOrder->orderNumber,
            'reference' => $purchaseOrder->reference,
        ]);

        $this->entityManager->persist($itemEvent);

        // Update Item quantities based on event
        // quantityOnHand increases when items are received
        $item->quantityOnHand += $quantity;
        
        // quantityOnOrder decreases when items are received
        $item->quantityOnOrder -= $quantity;
        
        // Recalculate quantityAvailable
        // quantityAvailable = quantityOnHand - quantityCommitted
        $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;

        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }
}
