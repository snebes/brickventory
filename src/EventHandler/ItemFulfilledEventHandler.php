<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Event\ItemFulfilledEvent;
use App\Repository\CostLayerRepository;
use App\Service\InventoryBalanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event handler that records item fulfillment events in the event store
 * and consumes cost layers in FIFO order for cost of goods sold calculation
 */
#[AsEventListener(event: ItemFulfilledEvent::class)]
class ItemFulfilledEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CostLayerRepository $costLayerRepository,
        private readonly InventoryBalanceService $inventoryBalanceService
    ) {
    }

    public function __invoke(ItemFulfilledEvent $event): void
    {
        $item = $event->getItem();
        $quantity = $event->getQuantity();
        $salesOrder = $event->getSalesOrder();
        $fulfillmentLine = $event->getFulfillmentLine();

        // Get location from fulfillment line or use default
        $locationId = null;
        if ($fulfillmentLine && $fulfillmentLine->itemFulfillment->fulfillFromLocation) {
            $locationId = $fulfillmentLine->itemFulfillment->fulfillFromLocation->id;
        } else {
            // Get default location
            $defaultLocation = $this->entityManager->getRepository(\App\Entity\Location::class)
                ->findOneBy(['locationCode' => 'DEFAULT']);
            if ($defaultLocation) {
                $locationId = $defaultLocation->id;
            }
        }

        // Consume cost layers in FIFO order (location-specific)
        $costResult = $this->consumeCostLayers($item, $quantity, $locationId);
        $totalCost = $costResult['totalCost'];
        $layersConsumed = $costResult['layersConsumed'];

        // Update inventory balance at location (NEW: location-specific tracking)
        if ($locationId) {
            $binLocation = $fulfillmentLine?->binLocation;
            $this->inventoryBalanceService->updateBalance(
                $item->id,
                $locationId,
                -$quantity,  // Negative because inventory decreases
                'fulfillment',
                $binLocation
            );
        }

        // Create event in event store
        $itemEvent = new ItemEvent();
        $itemEvent->item = $item;
        $itemEvent->eventType = 'item_fulfilled';
        $itemEvent->quantityChange = -$quantity; // Negative because inventory decreases
        $itemEvent->referenceType = 'sales_order';
        $itemEvent->referenceId = $salesOrder->id;
        $itemEvent->metadata = json_encode([
            'order_number' => $salesOrder->orderNumber,
            'cost_of_goods_sold' => $totalCost,
            'cost_layers_consumed' => $layersConsumed,
            'location_id' => $locationId,
        ]);

        $this->entityManager->persist($itemEvent);

        // DEPRECATED: Update Item quantities (for backward compatibility)
        // These will eventually be removed in favor of location-specific balances
        $item->quantityOnHand -= $quantity;
        $item->quantityCommitted -= $quantity;
        $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;

        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }

    /**
     * Consume cost layers in FIFO order and return total cost of goods sold
     * Now respects location boundaries for location-specific FIFO
     * 
     * @param Item $item
     * @param int $quantity
     * @param int|null $locationId Location from which items are being fulfilled
     * @return array{totalCost: float, layersConsumed: array<array{layerId: int, quantity: int, cost: float}>}
     */
    private function consumeCostLayers(Item $item, int $quantity, ?int $locationId = null): array
    {
        // Get layers filtered by location for location-specific FIFO
        $costLayers = $this->costLayerRepository->findAvailableByItem($item, $locationId);
        $remainingQuantity = $quantity;
        $totalCost = 0.0;
        $layersConsumed = [];

        foreach ($costLayers as $costLayer) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $result = $costLayer->consume($remainingQuantity);
            $consumed = $result['consumed'];
            $cost = $result['cost'];

            if ($consumed > 0) {
                $totalCost += $cost;
                $remainingQuantity -= $consumed;
                $layersConsumed[] = [
                    'layerId' => $costLayer->id,
                    'quantity' => $consumed,
                    'cost' => $cost,
                ];
                $this->entityManager->persist($costLayer);
            }
        }

        return [
            'totalCost' => $totalCost,
            'layersConsumed' => $layersConsumed,
        ];
    }
}
