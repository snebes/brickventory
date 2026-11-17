<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_product')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    public string $sku = '';

    #[ORM\Column(type: 'string')]
    public string $name = '';

    #[ORM\Column(type: 'text')]
    public string $description = '';

    #[ORM\Column(type: 'string', length: 30)]
    public string $price = '';

    #[ORM\Column(type: 'string', length: 30)]
    public string $cost = '';

    #[ORM\Column(type: 'boolean')]
    public bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    public ?Product $parent = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    public ?Category $category = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    public ?string $barcode = null;

    #[ORM\Column]
    public int $minStock = 0;

    #[ORM\Column(type: 'json')]
    public array $details = [];

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
