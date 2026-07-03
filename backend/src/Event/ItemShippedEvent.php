<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\ItemFulfillment;

/**
 * Event dispatched when an item fulfillment is marked as shipped.
 * 
 * This event triggers:
 * - Recording shipment event in event store
 * - Final inventory reduction (if not done at fulfillment creation)
 * - Updating sales order status
 */
class ItemShippedEvent
{
    public function __construct(
        private readonly ItemFulfillment $itemFulfillment,
        private readonly ?string $trackingNumber = null,
        private readonly ?string $shipMethod = null
    ) {
    }

    public function getItemFulfillment(): ItemFulfillment
    {
        return $this->itemFulfillment;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getShipMethod(): ?string
    {
        return $this->shipMethod;
    }
}
