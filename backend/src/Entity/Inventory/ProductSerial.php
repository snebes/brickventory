<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_product_serial')]
#[ORM\UniqueConstraint(name: 'inv_serial_identity', columns: ['product_id', 'serial_number'])]
class ProductSerial
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public Product $product;
    #[ORM\Column(length: 100)]
    public string $serialNumber = '';
    #[ORM\Column(length: 30)]
    public string $status = 'registered';
    #[ORM\Column(type: 'text')]
    public string $notes = '';
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
