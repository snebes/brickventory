<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Event\SalesOrderDeletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: SalesOrderDeletedEvent::class)]
class SalesOrderDeletedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(SalesOrderDeletedEvent $event): void
    {
        $orderState = $event->getOrderState();
        $orderId = $event->getOrderId();
        
        // Create order event in event store
        $orderEvent = new OrderEvent();
        $orderEvent->orderType = 'sales_order';
        $orderEvent->orderId = $orderId;
        $orderEvent->eventType = 'deleted';
        $orderEvent->previousState = json_encode($orderState);
        $orderEvent->newState = null;
        $orderEvent->metadata = json_encode([
            'order_number' => $orderState['orderNumber'] ?? null,
        ]);

        $this->entityManager->persist($orderEvent);

        // Reverse inventory changes for each line item
        // We need to find the original ItemEvents to get the committed/backordered quantities
        $lines = $orderState['lines'] ?? [];
        foreach ($lines as $lineData) {
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
            // the order was updated before deletion. We need to reverse the most recent quantities.
            $mostRecentEvent = $this->entityManager->getRepository(ItemEvent::class)
                ->createQueryBuilder('e')
                ->where('e.item = :item')
                ->andWhere('e.referenceType = :refType')
                ->andWhere('e.referenceId = :refId')
                ->andWhere('e.eventType IN (:eventTypes)')
                ->setParameter('item', $item)
                ->setParameter('refType', 'sales_order')
                ->setParameter('refId', $orderId)
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
                // Fallback: use the quantityOrdered from the line data
                // Assume all was committed (old behavior)
                $quantityToCommit = $lineData['quantityOrdered'] ?? 0;
            }
            
            // Create reversal event in event store
            $itemEvent = new ItemEvent();
            $itemEvent->item = $item;
            $itemEvent->eventType = 'sales_order_deleted';
            $itemEvent->quantityChange = $quantityToCommit + $quantityToBackorder; // Positive to reverse
            $itemEvent->referenceType = 'sales_order';
            $itemEvent->referenceId = $orderId;
            $itemEvent->metadata = json_encode([
                'order_number' => $orderState['orderNumber'] ?? null,
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

        $this->entityManager->flush();
    }
}
