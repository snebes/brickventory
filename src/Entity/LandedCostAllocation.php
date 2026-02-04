<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Landed Cost Allocation entity - tracks how landed costs are distributed across receipt lines
 */
#[ORM\Entity]
#[ORM\Table(name: 'landed_cost_allocation')]
#[ORM\Index(columns: ['landed_cost_id'], name: 'idx_lca_landed_cost')]
#[ORM\Index(columns: ['receipt_line_id'], name: 'idx_lca_receipt_line')]
#[ORM\Index(columns: ['cost_layer_id'], name: 'idx_lca_cost_layer')]
class LandedCostAllocation extends AbstractTransactionLineEntity
{
    #[ORM\ManyToOne(targetEntity: LandedCost::class, inversedBy: 'allocations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public LandedCost $landedCost;

    #[ORM\ManyToOne(targetEntity: ItemReceiptLine::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public ItemReceiptLine $receiptLine;

    #[ORM\ManyToOne(targetEntity: CostLayer::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public CostLayer $costLayer;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $allocatedAmount = 0.0;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4)]
    public float $allocationPercentage = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $quantity = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $originalUnitCost = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $adjustedUnitCost = 0.0;
}
