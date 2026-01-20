<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\InventoryAdjustment;
use App\Entity\InventoryAdjustmentLine;
use App\Entity\Item;

/**
 * Event dispatched when an inventory adjustment is applied
 */
class InventoryAdjustedEvent
{
    public function __construct(
        private readonly Item $item,
        private readonly int $quantityChange,
        private readonly InventoryAdjustment $inventoryAdjustment,
        private readonly ?InventoryAdjustmentLine $adjustmentLine = null
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

    public function getAdjustmentLine(): ?InventoryAdjustmentLine
    {
        return $this->adjustmentLine;
    }
}
