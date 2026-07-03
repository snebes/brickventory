<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\PurchaseOrder;

class PurchaseOrderCreatedEvent
{
    public function __construct(
        private readonly PurchaseOrder $purchaseOrder
    ) {
    }

    public function getPurchaseOrder(): PurchaseOrder
    {
        return $this->purchaseOrder;
    }
}
