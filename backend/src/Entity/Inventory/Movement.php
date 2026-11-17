<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_movement')]
class Movement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'integer')]
    public int $quantity = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityBefore = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityAfter = 0;

    #[ORM\Column(type: 'string', length: 30)]
    public string $type = '';

    #[ORM\Column(type: 'string')]
    public string $reference = '';

    #[ORM\Column(type: 'text')]
    public string $reason = '';

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?Location $location = null;

    #[ORM\Column(type: 'json')]
    public array $details = [];

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
