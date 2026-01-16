<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\PurchaseOrder;

class PurchaseOrderUpdatedEvent
{
    public function __construct(
        private readonly PurchaseOrder $purchaseOrder,
        private readonly ?array $previousState = null
    ) {
    }

    public function getPurchaseOrder(): PurchaseOrder
    {
        return $this->purchaseOrder;
    }

    public function getPreviousState(): ?array
    {
        return $this->previousState;
    }
}
