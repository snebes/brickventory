<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\SalesOrder;

class SalesOrderUpdatedEvent
{
    public function __construct(
        private readonly SalesOrder $salesOrder,
        private readonly ?array $previousState = null
    ) {
    }

    public function getSalesOrder(): SalesOrder
    {
        return $this->salesOrder;
    }

    public function getPreviousState(): ?array
    {
        return $this->previousState;
    }
}
