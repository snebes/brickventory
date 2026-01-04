<?php

declare(strict_types=1);

namespace App\Message\Query;

final class GetSalesOrdersQuery
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $orderDateFrom = null,
        public readonly ?string $orderDateTo = null,
        public readonly int $page = 1,
        public readonly int $perPage = 100
    ) {
    }
}
