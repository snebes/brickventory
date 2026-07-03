<?php

declare(strict_types=1);

namespace App\Event;

class PurchaseOrderDeletedEvent
{
    public function __construct(
        private readonly int $orderId,
        private readonly array $orderState
    ) {
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getOrderState(): array
    {
        return $this->orderState;
    }
}
