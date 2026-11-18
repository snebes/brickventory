<?php

declare(strict_types=1);

namespace App\Dto\Inventory;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProductSupplierPayload
{
    public function __construct(
        #[Assert\Positive] public ?int $supplier_id = null,
        #[Assert\Length(max: 255)] public ?string $supplier_sku = null,
        #[Assert\Regex('/^\d{1,10}(?:\.\d{1,2})?$/D')] public ?string $cost_price = null,
        #[Assert\Regex('/^[A-Z]{3}$/D')] public ?string $currency = null,
        #[Assert\Range(min: 1, max: 1000000000)] public ?int $minimum_order_quantity = null,
        #[Assert\Range(min: 0, max: 3650)] public ?int $lead_time_days = null,
        public ?bool $is_primary = null,
    ) {
    }

    public function values(): array
    {
        return array_filter(get_object_vars($this), static fn ($value) => null !== $value);
    }
}
