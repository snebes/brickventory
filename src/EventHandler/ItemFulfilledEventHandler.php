<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Event\ItemFulfilledEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event handler that records item fulfillment events in the event store
 */
#[AsEventListener(event: ItemFulfilledEvent::class)]
class ItemFulfilledEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(ItemFulfilledEvent $event): void
    {
        $item = $event->getItem();
        $quantity = $event->getQuantity();
        $salesOrder = $event->getSalesOrder();

        // Create event in event store
        $itemEvent = new ItemEvent();
        $itemEvent->item = $item;
        $itemEvent->eventType = 'item_fulfilled';
        $itemEvent->quantityChange = -$quantity; // Negative because inventory decreases
        $itemEvent->referenceType = 'sales_order';
        $itemEvent->referenceId = $salesOrder->id;
        $itemEvent->metadata = json_encode([
            'order_number' => $salesOrder->orderNumber,
        ]);

        $this->entityManager->persist($itemEvent);

        // Update Item quantities based on event
        // quantityOnHand decreases when items are fulfilled
        $item->quantityOnHand -= $quantity;
        
        // quantityBackOrdered decreases when items are fulfilled
        if ($item->quantityBackOrdered > 0) {
            $backOrderedToFulfill = min($quantity, $item->quantityBackOrdered);
            $item->quantityBackOrdered -= $backOrderedToFulfill;
        }
        
        // Recalculate quantityAvailable
        $item->quantityAvailable = $item->quantityOnHand + $item->quantityOnOrder - $item->quantityBackOrdered;

        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }
}
