<?php

declare(strict_types=1);

namespace App\Message\Command;

final class CreateSalesOrderCommand
{
    public function __construct(
        public readonly ?string $orderNumber,
        public readonly string $orderDate,
        public readonly string $status,
        public readonly ?string $notes,
        /** @var array<int, array{itemId: int, quantityOrdered: int}> */
        public readonly array $lines
    ) {
    }
}
