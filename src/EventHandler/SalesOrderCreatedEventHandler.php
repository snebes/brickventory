<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
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

        // Update inventory for each line item using event sourcing
        foreach ($salesOrder->lines as $line) {
            $item = $line->item;
            
            // Create event in event store
            $itemEvent = new ItemEvent();
            $itemEvent->item = $item;
            $itemEvent->eventType = 'sales_order_created';
            $itemEvent->quantityChange = -$line->quantityOrdered; // Negative because it's committed
            $itemEvent->referenceType = 'sales_order';
            $itemEvent->referenceId = $salesOrder->id;
            $itemEvent->metadata = json_encode([
                'order_number' => $salesOrder->orderNumber,
            ]);
            
            $this->entityManager->persist($itemEvent);
            
            // Update quantityCommitted when a sales order is created
            $item->quantityCommitted += $line->quantityOrdered;
            
            // Recalculate quantityAvailable
            // quantityAvailable = quantityOnHand - quantityCommitted
            $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;
            
            $this->entityManager->persist($item);
        }

        $this->entityManager->flush();
    }
}
