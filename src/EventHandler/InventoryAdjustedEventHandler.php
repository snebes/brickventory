<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Event\InventoryAdjustedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event handler that records inventory adjustment events in the event store
 * and immediately updates item quantities
 */
#[AsEventListener(event: InventoryAdjustedEvent::class)]
class InventoryAdjustedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(InventoryAdjustedEvent $event): void
    {
        $item = $event->getItem();
        $quantityChange = $event->getQuantityChange();
        $inventoryAdjustment = $event->getInventoryAdjustment();

        // Create event in event store
        $itemEvent = new ItemEvent();
        $itemEvent->item = $item;
        $itemEvent->eventType = 'inventory_adjusted';
        $itemEvent->quantityChange = $quantityChange;
        $itemEvent->referenceType = 'inventory_adjustment';
        $itemEvent->referenceId = $inventoryAdjustment->id;
        $itemEvent->metadata = json_encode([
            'adjustment_number' => $inventoryAdjustment->adjustmentNumber,
            'reason' => $inventoryAdjustment->reason,
            'memo' => $inventoryAdjustment->memo,
        ]);

        $this->entityManager->persist($itemEvent);

        // Update Item quantities based on event
        // quantityOnHand changes by the adjustment amount (can be positive or negative)
        $item->quantityOnHand += $quantityChange;
        
        // Recalculate quantityAvailable
        // quantityAvailable = quantityOnHand - quantityCommitted
        $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;

        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }
}
