<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Event\SalesOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: SalesOrderCreatedEvent::class)]
class SalesOrderCreatedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(SalesOrderCreatedEvent $event): void
    {
        $salesOrder = $event->getSalesOrder();

        // Create order event in event store for the sales order itself
        $orderEvent = new OrderEvent();
        $orderEvent->orderType = 'sales_order';
        $orderEvent->orderId = $salesOrder->id;
        $orderEvent->eventType = 'created';
        $orderEvent->previousState = null;
        $orderEvent->newState = json_encode($this->serializeSalesOrder($salesOrder));
        $orderEvent->metadata = json_encode([
            'order_number' => $salesOrder->orderNumber,
        ]);
        
        $this->entityManager->persist($orderEvent);

        // Update inventory for each line item using event sourcing
        // Following Netsuite sales order logic:
        // - If ordered quantity <= available, commit the full quantity
        // - If ordered quantity > available, commit what's available and backorder the rest
        foreach ($salesOrder->lines as $line) {
            $item = $line->item;
            $quantityOrdered = $line->quantityOrdered;
            
            // Calculate how much can be committed vs backordered
            // Use current quantityAvailable (before this order's adjustments)
            $currentAvailable = max(0, $item->quantityAvailable);
            
            if ($quantityOrdered <= $currentAvailable) {
                // Full quantity can be committed
                $quantityToCommit = $quantityOrdered;
                $quantityToBackorder = 0;
            } else {
                // Partial commitment, rest goes to backorder
                $quantityToCommit = $currentAvailable;
                $quantityToBackorder = $quantityOrdered - $currentAvailable;
            }
            
            // Create event in event store for item inventory
            $itemEvent = new ItemEvent();
            $itemEvent->item = $item;
            $itemEvent->eventType = 'sales_order_created';
            $itemEvent->quantityChange = -$quantityOrdered; // Negative because it's committed/backordered
            $itemEvent->referenceType = 'sales_order';
            $itemEvent->referenceId = $salesOrder->id;
            $itemEvent->metadata = json_encode([
                'order_number' => $salesOrder->orderNumber,
                'quantity_committed' => $quantityToCommit,
                'quantity_backordered' => $quantityToBackorder,
            ]);
            
            $this->entityManager->persist($itemEvent);
            
            // Update quantityCommitted when a sales order is created
            $item->quantityCommitted += $quantityToCommit;
            
            // Update quantityBackOrdered if order exceeds available
            $item->quantityBackOrdered += $quantityToBackorder;
            
            // Recalculate quantityAvailable
            // quantityAvailable = quantityOnHand - quantityCommitted
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
