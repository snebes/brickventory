<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_product_supplier')]
#[ORM\UniqueConstraint(name: 'inv_product_supplier_pair', columns: ['product_id', 'supplier_id'])]
class ProductSupplier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Product $product;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public Supplier $supplier;

    #[ORM\Column(length: 255)]
    public string $supplierSku = '';

    #[ORM\Column(length: 30)]
    public string $costPrice = '0.00';

    #[ORM\Column(length: 3)]
    public string $currency = 'USD';

    #[ORM\Column]
    public int $minimumOrderQuantity = 1;

    #[ORM\Column]
    public int $leadTimeDays = 0;

    #[ORM\Column]
    public bool $isPrimary = false;
}
