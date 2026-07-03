<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Event\InventoryAdjustedEvent;
use App\Service\InventoryBalanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event handler that records inventory adjustment events in the event store
 * and updates inventory balances at the specified location.
 * 
 * Following NetSuite ERP pattern: the location is required on the header record
 * and is used to determine where inventory quantities are adjusted.
 */
#[AsEventListener(event: InventoryAdjustedEvent::class)]
class InventoryAdjustedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryBalanceService $inventoryBalanceService
    ) {
    }

    public function __invoke(InventoryAdjustedEvent $event): void
    {
        $item = $event->getItem();
        $quantityChange = $event->getQuantityChange();
        $inventoryAdjustment = $event->getInventoryAdjustment();
        $adjustmentLine = $event->getAdjustmentLine();

        // Get location from adjustment header (required in NetSuite ERP pattern)
        $location = $inventoryAdjustment->location;
        $locationId = $location->id;

        // Update inventory balance at the header location
        $binLocation = $adjustmentLine?->binLocation;
        $transactionType = $quantityChange > 0 ? 'adjustment_increase' : 'adjustment_decrease';
        
        $this->inventoryBalanceService->updateBalance(
            $item->id,
            $locationId,
            $quantityChange,
            $transactionType,
            $binLocation
        );

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
            'location_id' => $locationId,
            'location_code' => $location->locationCode,
            'location_name' => $location->locationName,
            'bin_location' => $adjustmentLine?->binLocation,
        ]);

        $this->entityManager->persist($itemEvent);

        // DEPRECATED: Update Item quantities (for backward compatibility)
        // These will eventually be removed in favor of location-specific balances
        $item->quantityOnHand += $quantityChange;
        $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;

        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }
}
