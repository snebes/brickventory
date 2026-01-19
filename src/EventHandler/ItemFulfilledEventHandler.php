<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Event\ItemFulfilledEvent;
use App\Repository\CostLayerRepository;
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
        private readonly CostLayerRepository $costLayerRepository
    ) {
    }

    public function __invoke(ItemFulfilledEvent $event): void
    {
        $item = $event->getItem();
        $quantity = $event->getQuantity();
        $salesOrder = $event->getSalesOrder();

        // Consume cost layers in FIFO order
        $costResult = $this->consumeCostLayers($item, $quantity);
        $totalCost = $costResult['totalCost'];
        $layersConsumed = $costResult['layersConsumed'];

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
        ]);

        $this->entityManager->persist($itemEvent);

        // Update Item quantities based on event
        // quantityOnHand decreases when items are fulfilled
        $item->quantityOnHand -= $quantity;
        
        // quantityCommitted decreases when items are fulfilled (order is being shipped)
        $item->quantityCommitted -= $quantity;
        
        // Recalculate quantityAvailable
        // quantityAvailable = quantityOnHand - quantityCommitted
        $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;

        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }

    /**
     * Consume cost layers in FIFO order and return total cost of goods sold
     * 
     * @param \App\Entity\Item $item
     * @param int $quantity
     * @return array{totalCost: float, layersConsumed: array<array{layerId: int, quantity: int, cost: float}>}
     */
    private function consumeCostLayers($item, int $quantity): array
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
}
