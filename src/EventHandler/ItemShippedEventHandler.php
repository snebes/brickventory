<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Event\ItemShippedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event handler for ItemShippedEvent.
 * 
 * Records the shipment event in the event store and updates
 * tracking information on the fulfillment.
 */
#[AsEventListener(event: ItemShippedEvent::class)]
class ItemShippedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(ItemShippedEvent $event): void
    {
        $fulfillment = $event->getItemFulfillment();
        $salesOrder = $fulfillment->salesOrder;

        // Update fulfillment with shipment details
        $fulfillment->markAsShipped(
            $event->getTrackingNumber(),
            $event->getShipMethod()
        );

        // Create order event in event store for the shipment
        $orderEvent = new OrderEvent();
        $orderEvent->orderType = 'item_fulfillment';
        $orderEvent->orderId = $fulfillment->id;
        $orderEvent->eventType = 'shipped';
        $orderEvent->previousState = null;
        $orderEvent->newState = json_encode([
            'id' => $fulfillment->id,
            'fulfillmentNumber' => $fulfillment->fulfillmentNumber,
            'status' => $fulfillment->status,
            'trackingNumber' => $fulfillment->trackingNumber,
            'shipMethod' => $fulfillment->shipMethod,
            'shippedAt' => $fulfillment->shippedAt?->format('Y-m-d H:i:s'),
        ]);
        $orderEvent->metadata = json_encode([
            'fulfillment_number' => $fulfillment->fulfillmentNumber,
            'sales_order_id' => $salesOrder->id,
            'sales_order_number' => $salesOrder->orderNumber,
            'tracking_number' => $event->getTrackingNumber(),
            'ship_method' => $event->getShipMethod(),
        ]);

        $this->entityManager->persist($orderEvent);

        // Create item events for each line to record the shipment
        foreach ($fulfillment->lines as $fulfillmentLine) {
            $itemEvent = new ItemEvent();
            $itemEvent->item = $fulfillmentLine->item;
            $itemEvent->eventType = 'item_shipped';
            $itemEvent->quantityChange = 0; // No additional inventory change at shipment
            $itemEvent->referenceType = 'item_fulfillment';
            $itemEvent->referenceId = $fulfillment->id;
            $itemEvent->metadata = json_encode([
                'fulfillment_number' => $fulfillment->fulfillmentNumber,
                'tracking_number' => $fulfillment->trackingNumber,
                'shipped_at' => $fulfillment->shippedAt?->format('Y-m-d H:i:s'),
            ]);

            $this->entityManager->persist($itemEvent);
        }

        $this->entityManager->persist($fulfillment);
        $this->entityManager->flush();
    }
}
