<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace App\Dto\Inventory;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Payload
{
    public function __construct(
        #[Assert\Length(max: 10000)]
        public ?string $name = null,
        #[Assert\Length(max: 10000)]
        public ?string $sku = null,
        #[Assert\Length(max: 10000)]
        public ?string $description = null,
        #[Assert\Length(max: 10000)]
        public ?string $price = null,
        #[Assert\Length(max: 10000)]
        public ?string $cost = null,
        #[Assert\Length(max: 10000)]
        public ?string $email = null,
        #[Assert\Length(max: 10000)]
        public ?string $phone = null,
        #[Assert\Length(max: 10000)]
        public ?string $company_name = null,
        #[Assert\Length(max: 10000)]
        public ?string $contact_name = null,
        #[Assert\Length(max: 10000)]
        public ?string $billing_address = null,
        #[Assert\Length(max: 10000)]
        public ?string $shipping_address = null,
        #[Assert\Length(max: 10000)]
        public ?string $address = null,
        #[Assert\Length(max: 10000)]
        public ?string $city = null,
        #[Assert\Length(max: 10000)]
        public ?string $country = null,
        #[Assert\Length(max: 10000)]
        public ?string $tax_id = null,
        #[Assert\Length(max: 10000)]
        public ?string $payment_terms = null,
        #[Assert\Length(max: 10000)]
        public ?string $currency = null,
        #[Assert\Length(max: 10000)]
        public ?string $notes = null,
        #[Assert\Length(max: 10000)]
        public ?string $code = null,
        public ?bool $is_active = null,
        public ?int $customer_id = null,
        public ?int $supplier_id = null,
        #[Assert\Length(max: 10000)]
        public ?string $customer_name = null,
        public ?int $location_id = null,
        public ?int $from_location_id = null,
        public ?int $to_location_id = null,
        public ?int $product_id = null,
        public ?int $quantity = null,
        #[Assert\Length(max: 10000)]
        public ?string $type = null,
        #[Assert\Length(max: 10000)]
        public ?string $reason = null,
        #[Assert\Length(max: 10000)]
        public ?string $status = null,
        #[Assert\Count(max: 500)]
        public ?array $items = null,
        public ?string $tax = null,
        public ?string $shipping = null,
        public ?string $discount_type = null,
        public ?string $discount_value = null,
        public ?string $order_date = null,
        public ?string $expected_date = null,
        public ?string $customer_email = null,
        public ?string $customer_address = null,
        public ?string $source = null,
        public ?string $external_id = null,
        public ?string $state = null,
        public ?string $zip_code = null,
        public ?string $website = null,
        public ?int $rating = null,
        public ?array $metadata = null,
        public ?string $tracking_number = null,
        public ?string $carrier = null,
        public ?string $barcode = null,
        public ?int $min_stock = null,
        public ?int $category_id = null,
        public ?int $warehouse_id = null,
        public ?int $parent_id = null,
        public ?array $options = null,
        public ?array $attributes = null,
        public ?string $aisle = null,
        public ?string $shelf = null,
        public ?string $bin = null,
        public ?string $unit = null,
        public ?int $product_variant_id = null,
        public ?string $amount = null,
        public ?string $reference = null,
        public ?string $method = null,
        public ?array $rows = null,
        public ?string $tracking = null,
        public ?string $batch = null,
        public ?array $serials = null,
        public ?string $expires_at = null,
        public ?bool $approval_required = null,
        public ?string $billing_city = null,
        public ?string $billing_state = null,
        public ?string $billing_zip_code = null,
        public ?string $billing_country = null,
        public ?string $shipping_city = null,
        public ?string $shipping_state = null,
        public ?string $shipping_zip_code = null,
        public ?string $shipping_country = null,
        public ?string $credit_limit = null,
        public ?int $order_id = null,
        public ?string $shipping_method = null,
        public ?string $estimated_arrival = null,
        public ?int $reorder_point = null,
        public ?int $reorder_quantity = null,
        public ?int $max_stock = null,
    ) {
    }

    public function values(): array
    {
        return array_filter(get_object_vars($this), static fn ($v) => null !== $v);
    }
}
