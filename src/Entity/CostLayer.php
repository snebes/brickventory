<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

use App\Repository\CostLayerRepository;

/**
 * Represents a cost layer for FIFO inventory valuation.
 * Each time items are received at a specific cost, a new cost layer is created.
 * When items are fulfilled, the oldest cost layers are consumed first (FIFO).
 */
#[ORM\Entity(repositoryClass: CostLayerRepository::class)]
#[ORM\Index(columns: ['item_id', 'receipt_date'])]
#[ORM\Index(columns: ['item_id', 'layer_type', 'quality_status'])]
#[ORM\Index(columns: ['vendor_id'], name: 'idx_cost_layer_vendor')]
class CostLayer
{
    // Layer Type Constants
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_MANUFACTURING = 'manufacturing';

    public const VALID_TYPES = [
        self::TYPE_RECEIPT,
        self::TYPE_ADJUSTMENT,
        self::TYPE_TRANSFER_IN,
        self::TYPE_MANUFACTURING,
    ];

    // Quality Status Constants
    public const QUALITY_AVAILABLE = 'available';
    public const QUALITY_QUARANTINE = 'quarantine';
    public const QUALITY_REJECTED = 'rejected';

    public const VALID_QUALITY_STATUSES = [
        self::QUALITY_AVAILABLE,
        self::QUALITY_QUARANTINE,
        self::QUALITY_REJECTED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\ManyToOne(targetEntity: ItemReceiptLine::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?ItemReceiptLine $itemReceiptLine = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_TYPES)]
    public string $layerType = self::TYPE_RECEIPT;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_QUALITY_STATUSES)]
    public string $qualityStatus = self::QUALITY_AVAILABLE;

    /**
     * Original quantity received in this cost layer
     */
    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $quantityReceived = 0;

    /**
     * Remaining quantity in this cost layer (decreases as items are fulfilled)
     */
    #[ORM\Column(type: 'integer')]
    public int $quantityRemaining = 0;

    /**
     * Cost per unit for this cost layer (original cost + landed cost adjustments)
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $unitCost = 0.0;

    /**
     * Original cost per unit before landed cost adjustments
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $originalUnitCost = 0.0;

    /**
     * Total landed cost adjustments per unit
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $landedCostAdjustments = 0.0;

    /**
     * Date this cost layer was created (used for FIFO ordering)
     */
    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $receiptDate;

    /**
     * Vendor this cost layer was received from
     */
    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Vendor $vendor = null;

    /**
     * Timestamp of last cost adjustment (for audit trail)
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $lastCostAdjustment = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $sourceType = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $sourceReference = null;

    #[ORM\Column(type: 'boolean')]
    public bool $voided = false;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $voidReason = null;

    /**
     * Reference to transfer number if this layer was created from an inter-location transfer
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $transferReference = null;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->receiptDate = new \DateTime();
    }

    /**
     * Calculate total cost of remaining inventory in this layer
     */
    public function getTotalCost(): float
    {
        return $this->quantityRemaining * $this->unitCost;
    }

    /**
     * Consume quantity from this cost layer and return the cost of consumed items
     * 
     * @param int $quantity Quantity to consume
     * @return array{consumed: int, cost: float} Array with consumed quantity and total cost
     */
    public function consume(int $quantity): array
    {
        $consumed = min($quantity, $this->quantityRemaining);
        $cost = $consumed * $this->unitCost;
        $this->quantityRemaining -= $consumed;
        
        return [
            'consumed' => $consumed,
            'cost' => $cost,
        ];
    }

    /**
     * Apply landed cost adjustment to this layer
     */
    public function applyLandedCost(float $perUnitAdjustment): void
    {
        $this->landedCostAdjustments += $perUnitAdjustment;
        $this->unitCost = $this->originalUnitCost + $this->landedCostAdjustments;
        $this->lastCostAdjustment = new \DateTime();
    }
}
