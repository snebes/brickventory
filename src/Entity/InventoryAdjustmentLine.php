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
#[ORM\Index(columns: ['item_id', 'inventory_adjustment_id'])]
class InventoryAdjustmentLine
{
    // Adjustment Line Type Constants
    public const TYPE_QUANTITY = 'quantity';
    public const TYPE_VALUE = 'value';
    public const TYPE_BOTH = 'both';

    public const VALID_TYPES = [
        self::TYPE_QUANTITY,
        self::TYPE_VALUE,
        self::TYPE_BOTH,
    ];

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

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_TYPES)]
    public string $adjustmentType = self::TYPE_QUANTITY;

    #[ORM\Column(type: 'integer')]
    public int $quantityChange = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $quantityBefore = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $quantityAfter = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $currentUnitCost = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $adjustmentUnitCost = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $newUnitCost = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $totalCostImpact = 0.0;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $binLocation = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $lotNumber = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $serialNumber = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $expenseAccountId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $layersAffected = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
    }
}
