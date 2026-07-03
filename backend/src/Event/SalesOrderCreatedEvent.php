<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\SalesOrder;

class SalesOrderCreatedEvent
{
    public function __construct(
        private readonly SalesOrder $salesOrder
    ) {
    }

    public function getSalesOrder(): SalesOrder
    {
        return $this->salesOrder;
    }
}
