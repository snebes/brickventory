<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Item;
use App\Entity\PurchaseOrder;

/**
 * Event dispatched when items are received from a purchase order
 */
class ItemReceivedEvent
{
    public function __construct(
        private readonly Item $item,
        private readonly int $quantity,
        private readonly PurchaseOrder $purchaseOrder
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

    public function getPurchaseOrder(): PurchaseOrder
    {
        return $this->purchaseOrder;
    }
}
