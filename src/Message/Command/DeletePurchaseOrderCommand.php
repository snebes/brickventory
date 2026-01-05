<?php

declare(strict_types=1);

namespace App\Message\Command;

final class DeletePurchaseOrderCommand
{
    public function __construct(
        public readonly int $id
    ) {
    }
}
