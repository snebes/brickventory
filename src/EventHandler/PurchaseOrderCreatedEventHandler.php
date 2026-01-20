<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Event\PurchaseOrderCreatedEvent;
use App\Service\InventoryBalanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PurchaseOrderCreatedEvent::class)]
class PurchaseOrderCreatedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryBalanceService $inventoryBalanceService
    ) {
    }

    public function __invoke(PurchaseOrderCreatedEvent $event): void
    {
        $purchaseOrder = $event->getPurchaseOrder();

        // Create order event in event store for the purchase order itself
        $orderEvent = new OrderEvent();
        $orderEvent->orderType = 'purchase_order';
        $orderEvent->orderId = $purchaseOrder->id;
        $orderEvent->eventType = 'created';
        $orderEvent->previousState = null;
        $orderEvent->newState = json_encode($this->serializePurchaseOrder($purchaseOrder));
        $orderEvent->metadata = json_encode([
            'order_number' => $purchaseOrder->orderNumber,
            'reference' => $purchaseOrder->reference,
        ]);
        
        $this->entityManager->persist($orderEvent);

        // Get location from PO
        $location = $purchaseOrder->location;
        if (!$location) {
            throw new \RuntimeException('Purchase Order must have a location');
        }

        // Update inventory for each line item using InventoryBalanceService
        foreach ($purchaseOrder->lines as $line) {
            $item = $line->item;
            
            // Create event in event store for item inventory
            $itemEvent = new ItemEvent();
            $itemEvent->item = $item;
            $itemEvent->eventType = 'purchase_order_created';
            $itemEvent->quantityChange = $line->quantityOrdered;
            $itemEvent->referenceType = 'purchase_order';
            $itemEvent->referenceId = $purchaseOrder->id;
            $itemEvent->metadata = json_encode([
                'order_number' => $purchaseOrder->orderNumber,
                'reference' => $purchaseOrder->reference,
                'location_id' => $location->id,
                'location_code' => $location->locationCode,
            ]);
            
            $this->entityManager->persist($itemEvent);
            
            // Update quantityOnOrder at the location using InventoryBalanceService
            $this->inventoryBalanceService->updateBalance(
                $item->id,
                $location->id,
                $line->quantityOrdered,
                'order'
            );
            
            // DEPRECATED: Update item-level quantityOnOrder (for backward compatibility)
            // This will eventually be removed in favor of location-specific balances
            $item->quantityOnOrder += $line->quantityOrdered;
            $this->entityManager->persist($item);
        }

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
