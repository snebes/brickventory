<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Item;
use App\Entity\SalesOrder;
use App\Entity\ItemFulfillmentLine;

/**
 * Event dispatched when items are fulfilled for a sales order
 */
class ItemFulfilledEvent
{
    public function __construct(
        private readonly Item $item,
        private readonly int $quantity,
        private readonly SalesOrder $salesOrder,
        private readonly ?ItemFulfillmentLine $fulfillmentLine = null
    ) {
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getSalesOrder(): SalesOrder
    {
        return $this->salesOrder;
    }

    public function getFulfillmentLine(): ?ItemFulfillmentLine
    {
        return $this->fulfillmentLine;
    }
}
