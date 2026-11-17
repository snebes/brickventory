<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_balance')]
#[ORM\UniqueConstraint(name: 'inv_balance_target', columns: ['product_id', 'location_id'])]
class Balance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'integer')]
    public int $quantity = 0;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?Location $location = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
