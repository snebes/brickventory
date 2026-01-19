<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Event\SalesOrderUpdatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: SalesOrderUpdatedEvent::class)]
class SalesOrderUpdatedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(SalesOrderUpdatedEvent $event): void
    {
        $salesOrder = $event->getSalesOrder();
        $previousState = $event->getPreviousState();

        // Create order event in event store
        $orderEvent = new OrderEvent();
        $orderEvent->orderType = 'sales_order';
        $orderEvent->orderId = $salesOrder->id;
        $orderEvent->eventType = 'updated';
        $orderEvent->previousState = $previousState ? json_encode($previousState) : null;
        $orderEvent->newState = json_encode($this->serializeSalesOrder($salesOrder));
        $orderEvent->metadata = json_encode([
            'order_number' => $salesOrder->orderNumber,
        ]);

        $this->entityManager->persist($orderEvent);

        // Step 1: Reverse the inventory changes from the previous state
        if ($previousState && isset($previousState['lines'])) {
            foreach ($previousState['lines'] as $lineData) {
                $itemId = $lineData['item']['id'] ?? $lineData['itemId'] ?? null;
                if (!$itemId) {
                    continue;
                }
                
                $item = $this->entityManager->getRepository(Item::class)->find($itemId);
                if (!$item) {
                    continue;
                }
                
                // Find the most recent ItemEvent for this sales order to get current committed/backordered quantities
                // This could be either 'sales_order_created' or 'sales_order_updated' depending on whether
                // the order has been updated before. We need to reverse the most recent quantities.
                $mostRecentEvent = $this->entityManager->getRepository(ItemEvent::class)
                    ->createQueryBuilder('e')
                    ->where('e.item = :item')
                    ->andWhere('e.referenceType = :refType')
                    ->andWhere('e.referenceId = :refId')
                    ->andWhere('e.eventType IN (:eventTypes)')
                    ->setParameter('item', $item)
                    ->setParameter('refType', 'sales_order')
                    ->setParameter('refId', $salesOrder->id)
                    ->setParameter('eventTypes', ['sales_order_created', 'sales_order_updated'])
                    ->orderBy('e.eventDate', 'DESC')
                    ->addOrderBy('e.id', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                $quantityToCommit = 0;
                $quantityToBackorder = 0;
                
                if ($mostRecentEvent && $mostRecentEvent->metadata) {
                    $metadata = json_decode($mostRecentEvent->metadata, true);
                    $quantityToCommit = $metadata['quantity_committed'] ?? 0;
                    $quantityToBackorder = $metadata['quantity_backordered'] ?? 0;
                } else {
                    // Fallback: use the quantityOrdered from the previous line data
                    $quantityToCommit = $lineData['quantityOrdered'] ?? 0;
                }
                
                // Create reversal event in event store
                $itemEvent = new ItemEvent();
                $itemEvent->item = $item;
                $itemEvent->eventType = 'sales_order_updated_reversal';
                $itemEvent->quantityChange = $quantityToCommit + $quantityToBackorder;
                $itemEvent->referenceType = 'sales_order';
                $itemEvent->referenceId = $salesOrder->id;
                $itemEvent->metadata = json_encode([
                    'order_number' => $salesOrder->orderNumber,
                    'quantity_committed_reversed' => $quantityToCommit,
                    'quantity_backordered_reversed' => $quantityToBackorder,
                ]);
                
                $this->entityManager->persist($itemEvent);
                
                // Reverse the committed and backordered quantities
                $item->quantityCommitted = max(0, $item->quantityCommitted - $quantityToCommit);
                $item->quantityBackOrdered = max(0, $item->quantityBackOrdered - $quantityToBackorder);
                
                // Recalculate quantityAvailable
                $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;
                
                $this->entityManager->persist($item);
            }
        }

        // Step 2: Apply the new inventory changes based on current lines
        // Following Netsuite sales order logic
        foreach ($salesOrder->lines as $line) {
            $item = $line->item;
            $quantityOrdered = $line->quantityOrdered;
            
            // Calculate how much can be committed vs backordered
            $currentAvailable = max(0, $item->quantityAvailable);
            
            if ($quantityOrdered <= $currentAvailable) {
                $quantityToCommit = $quantityOrdered;
                $quantityToBackorder = 0;
            } else {
                $quantityToCommit = $currentAvailable;
                $quantityToBackorder = $quantityOrdered - $currentAvailable;
            }
            
            // Create event in event store for item inventory
            $itemEvent = new ItemEvent();
            $itemEvent->item = $item;
            $itemEvent->eventType = 'sales_order_updated';
            $itemEvent->quantityChange = -$quantityOrdered;
            $itemEvent->referenceType = 'sales_order';
            $itemEvent->referenceId = $salesOrder->id;
            $itemEvent->metadata = json_encode([
                'order_number' => $salesOrder->orderNumber,
                'quantity_committed' => $quantityToCommit,
                'quantity_backordered' => $quantityToBackorder,
            ]);
            
            $this->entityManager->persist($itemEvent);
            
            // Update quantityCommitted and quantityBackOrdered
            $item->quantityCommitted += $quantityToCommit;
            $item->quantityBackOrdered += $quantityToBackorder;
            
            // Recalculate quantityAvailable
            $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;
            
            $this->entityManager->persist($item);
        }

        $this->entityManager->flush();
    }

    private function serializeSalesOrder($so): array
    {
        return [
            'id' => $so->id,
            'orderNumber' => $so->orderNumber,
            'orderDate' => $so->orderDate->format('Y-m-d H:i:s'),
            'status' => $so->status,
            'notes' => $so->notes,
            'lines' => array_map(function ($line) {
                return [
                    'id' => $line->id ?? null,
                    'itemId' => $line->item->id,
                    'itemName' => $line->item->itemName,
                    'quantityOrdered' => $line->quantityOrdered,
                ];
            }, $so->lines->toArray()),
        ];
    }
}
