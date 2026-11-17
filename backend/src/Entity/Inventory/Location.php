<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_location')]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'string')]
    public string $name = '';

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    public string $code = '';

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    public ?Warehouse $warehouse = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    public ?Location $parent = null;

    #[ORM\Column(type: 'json')]
    public array $details = [];

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
