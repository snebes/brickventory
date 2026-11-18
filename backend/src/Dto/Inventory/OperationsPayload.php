<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Dto\Inventory;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OperationsPayload
{
    public function __construct(
        #[Assert\Count(max: 500), Assert\All([new Assert\Type('integer'), new Assert\Positive()]), Assert\Unique]
        public ?array $ids = null,
        public ?int $category_id = null,
        #[Assert\Length(max: 100)] public ?string $sku = null,
        #[Assert\Length(max: 255)] public ?string $name = null,
        public ?string $type = null,
        public ?string $value = null,
        #[Assert\Count(max: 100)] public ?array $levels = null,
        public ?string $frequency = null,
        public ?string $scope_type = null,
        public ?int $scope_id = null,
        public ?int $products_per_run = null,
        public ?bool $is_active = null,
        public ?string $next_run_at = null,
        public ?int $quantity = null,
        #[Assert\Count(max: 1000)] public ?array $serials = null,
        public ?string $expires_at = null,
        #[Assert\Length(max: 100)] public ?string $serial_number = null,
        public ?string $status = null,
        #[Assert\Length(max: 4000)] public ?string $notes = null,
        public ?int $location_id = null,
        #[Assert\Length(max: 255)] public ?string $carrier = null,
        #[Assert\Length(max: 255)] public ?string $tracking_number = null,
    ) {
    }

    public function values(): array
    {
        return array_filter(get_object_vars($this), static fn ($v) => null !== $v);
    }
}
