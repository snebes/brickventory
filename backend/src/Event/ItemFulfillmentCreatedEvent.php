<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\ItemFulfillment;

/**
 * Event dispatched when an item fulfillment is created.
 * 
 * This event triggers:
 * - Recording fulfillment event in event store
 * - Updating inventory committed quantities
 * - Creating accounting entries (COGS and Inventory Asset reduction)
 */
class ItemFulfillmentCreatedEvent
{
    public function __construct(
        private readonly ItemFulfillment $itemFulfillment
    ) {
    }

    public function getItemFulfillment(): ItemFulfillment
    {
        return $this->itemFulfillment;
    }
}
