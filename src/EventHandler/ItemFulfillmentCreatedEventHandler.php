<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Entity\SalesOrder;
use App\Event\ItemFulfillmentCreatedEvent;
use App\Repository\CostLayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event handler for ItemFulfillmentCreatedEvent.
 * 
 * Handles inventory reduction when items are fulfilled:
 * - Decreases quantityOnHand
 * - Decreases quantityCommitted
 * - Recalculates quantityAvailable
 * - Consumes cost layers in FIFO order for COGS calculation
 * - Updates sales order line fulfilled quantities
 * - Updates sales order status
 */
#[AsEventListener(event: ItemFulfillmentCreatedEvent::class)]
class ItemFulfillmentCreatedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CostLayerRepository $costLayerRepository
    ) {
    }

    public function __invoke(ItemFulfillmentCreatedEvent $event): void
    {
        $fulfillment = $event->getItemFulfillment();
        $salesOrder = $fulfillment->salesOrder;
        $totalCogs = 0.0;
        $allLayersConsumed = [];

        // Create order event in event store for the fulfillment
        $orderEvent = new OrderEvent();
        $orderEvent->orderType = 'item_fulfillment';
        $orderEvent->orderId = $fulfillment->id;
        $orderEvent->eventType = 'created';
        $orderEvent->previousState = null;
        $orderEvent->newState = json_encode($this->serializeFulfillment($fulfillment));
        $orderEvent->metadata = json_encode([
            'fulfillment_number' => $fulfillment->fulfillmentNumber,
            'sales_order_id' => $salesOrder->id,
            'sales_order_number' => $salesOrder->orderNumber,
        ]);

        $this->entityManager->persist($orderEvent);

        // Process each fulfillment line
        foreach ($fulfillment->lines as $fulfillmentLine) {
            $item = $fulfillmentLine->item;
            $salesOrderLine = $fulfillmentLine->salesOrderLine;
            $quantityFulfilled = $fulfillmentLine->quantityFulfilled;

            // Consume cost layers in FIFO order for COGS calculation
            $costResult = $this->consumeCostLayers($item, $quantityFulfilled);
            $lineCogs = $costResult['totalCost'];
            $totalCogs += $lineCogs;
            $allLayersConsumed = array_merge($allLayersConsumed, $costResult['layersConsumed']);

            // Create event in event store for item inventory
            $itemEvent = new ItemEvent();
            $itemEvent->item = $item;
            $itemEvent->eventType = 'item_fulfilled';
            $itemEvent->quantityChange = -$quantityFulfilled; // Negative because inventory decreases
            $itemEvent->referenceType = 'item_fulfillment';
            $itemEvent->referenceId = $fulfillment->id;
            $itemEvent->metadata = json_encode([
                'fulfillment_number' => $fulfillment->fulfillmentNumber,
                'sales_order_number' => $salesOrder->orderNumber,
                'sales_order_line_id' => $salesOrderLine->id,
                'cost_of_goods_sold' => $lineCogs,
                'cost_layers_consumed' => $costResult['layersConsumed'],
            ]);

            $this->entityManager->persist($itemEvent);

            // Update Item quantities
            // quantityOnHand decreases when items are fulfilled
            $item->quantityOnHand -= $quantityFulfilled;
            
            // quantityCommitted decreases when items are fulfilled (order is being shipped)
            $item->quantityCommitted = max(0, $item->quantityCommitted - $quantityFulfilled);
            
            // Recalculate quantityAvailable
            // quantityAvailable = quantityOnHand - quantityCommitted
            $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;

            $this->entityManager->persist($item);

            // Update sales order line fulfilled quantity
            $salesOrderLine->quantityFulfilled += $quantityFulfilled;
            
            // Update committed quantity on line (reduce it)
            $salesOrderLine->quantityCommitted = max(0, $salesOrderLine->quantityCommitted - $quantityFulfilled);

            $this->entityManager->persist($salesOrderLine);
        }

        // Update sales order status based on fulfillment state
        $salesOrder->updateFulfillmentStatus();
        $this->entityManager->persist($salesOrder);

        $this->entityManager->flush();
    }

    /**
     * Consume cost layers in FIFO order and return total cost of goods sold
     * 
     * @param Item $item
     * @param int $quantity
     * @return array{totalCost: float, layersConsumed: array<array{layerId: int, quantity: int, cost: float}>}
     */
    private function consumeCostLayers(Item $item, int $quantity): array
    {
        $costLayers = $this->costLayerRepository->findAvailableByItem($item);
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

    private function serializeFulfillment($fulfillment): array
    {
        return [
            'id' => $fulfillment->id,
            'fulfillmentNumber' => $fulfillment->fulfillmentNumber,
            'salesOrderId' => $fulfillment->salesOrder->id,
            'salesOrderNumber' => $fulfillment->salesOrder->orderNumber,
            'fulfillmentDate' => $fulfillment->fulfillmentDate->format('Y-m-d H:i:s'),
            'status' => $fulfillment->status,
            'shipMethod' => $fulfillment->shipMethod,
            'trackingNumber' => $fulfillment->trackingNumber,
            'lines' => array_map(function ($line) {
                return [
                    'id' => $line->id ?? null,
                    'itemId' => $line->item->id,
                    'itemName' => $line->item->itemName,
                    'salesOrderLineId' => $line->salesOrderLine->id,
                    'quantityFulfilled' => $line->quantityFulfilled,
                ];
            }, $fulfillment->lines->toArray()),
        ];
    }
}
