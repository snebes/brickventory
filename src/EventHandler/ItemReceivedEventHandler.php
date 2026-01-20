<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Event\ItemReceivedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event handler that records item receipt events in the event store
 * Note: Cost layer creation is now handled by ItemReceiptService
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
        $unitCost = $event->getUnitCost();
        $receiptLine = $event->getReceiptLine();

        // Get location from receipt line
        $locationId = null;
        if ($receiptLine && $receiptLine->itemReceipt->receivedAtLocation) {
            $locationId = $receiptLine->itemReceipt->receivedAtLocation->id;
        }

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
            'unit_cost' => $unitCost,
            'vendor_id' => $purchaseOrder->vendor?->id,
            'vendor_name' => $purchaseOrder->vendor?->vendorName,
            'location_id' => $locationId,
            'bin_location' => $receiptLine?->binLocation,
        ]);

        $this->entityManager->persist($itemEvent);

        // Note: Cost layer creation, inventory balance updates are handled by ItemReceiptService
        // This handler only creates the event record for audit trail

        $this->entityManager->flush();
    }
}
