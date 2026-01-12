<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\InventoryAdjustment;
use App\Entity\Item;

/**
 * Event dispatched when an inventory adjustment is applied
 */
class InventoryAdjustedEvent
{
    public function __construct(
        private readonly Item $item,
        private readonly int $quantityChange,
        private readonly InventoryAdjustment $inventoryAdjustment
    ) {
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getQuantityChange(): int
    {
        return $this->quantityChange;
    }

    public function getInventoryAdjustment(): InventoryAdjustment
    {
        return $this->inventoryAdjustment;
    }
}
