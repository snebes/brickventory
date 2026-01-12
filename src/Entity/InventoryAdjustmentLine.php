<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Line item for an inventory adjustment.
 * Contains the item and quantity change (positive for additions, negative for subtractions).
 */
#[ORM\Entity]
class InventoryAdjustmentLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\ManyToOne(targetEntity: InventoryAdjustment::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public InventoryAdjustment $inventoryAdjustment;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\Column(type: 'integer')]
    public int $quantityChange = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
    }
}
