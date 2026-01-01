<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Item;
use App\Entity\SalesOrder;

/**
 * Event dispatched when items are fulfilled for a sales order
 */
class ItemFulfilledEvent
{
    public function __construct(
        private readonly Item $item,
        private readonly int $quantity,
        private readonly SalesOrder $salesOrder
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
}
