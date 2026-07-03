<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Line item for a physical count.
 * Records the counted quantity vs system quantity for each item.
 */
#[ORM\Entity]
#[ORM\Index(columns: ['physical_count_id', 'item_id'])]
class PhysicalCountLine extends AbstractTransactionLineEntity
{
    #[ORM\ManyToOne(targetEntity: PhysicalCount::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public PhysicalCount $physicalCount;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $locationId = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $binLocation = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $lotNumber = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $serialNumber = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $systemQuantity = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $countedQuantity = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $varianceQuantity = 0.0;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    public float $variancePercent = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $varianceValue = 0.0;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $countedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $countedAt = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $verifiedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $verifiedAt = null;

    #[ORM\Column(type: 'boolean')]
    public bool $recountRequired = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $recountQuantity = null;

    #[ORM\ManyToOne(targetEntity: InventoryAdjustmentLine::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?InventoryAdjustmentLine $adjustmentLine = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;


    public function calculateVariance(): void
    {
        if ($this->countedQuantity === null) {
            return;
        }

        $this->varianceQuantity = $this->countedQuantity - $this->systemQuantity;

        if (abs($this->systemQuantity) > 0.001) {
            $this->variancePercent = ($this->varianceQuantity / $this->systemQuantity) * 100;
        } else {
            $this->variancePercent = $this->countedQuantity > 0 ? 100.0 : 0.0;
        }
    }

    public function hasVariance(): bool
    {
        return abs($this->varianceQuantity) > 0.001;
    }

    public function isCounted(): bool
    {
        return $this->countedQuantity !== null;
    }
}
