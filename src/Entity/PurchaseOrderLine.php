<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
#[ORM\Table(name: 'purchase_order_line')]
class PurchaseOrderLine extends AbstractTransactionLineEntity
{
    #[ORM\ManyToOne(targetEntity: PurchaseOrder::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public PurchaseOrder $purchaseOrder;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $quantityOrdered = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityReceived = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityBilled = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $rate = 0.0;

    // Location for receiving (line-level override)
    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Location $receivingLocation = null;

    // Bin location for receiving
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $receivingBinLocation = null;

    // Tax information
    #[ORM\Column(type: 'decimal', precision: 5, scale: 4, nullable: true)]
    public ?float $taxRate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $taxAmount = null;

    // For future accounting integration
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $expenseAccountId = null;

    // Line-level delivery date
    #[ORM\Column(type: 'date', nullable: true)]
    public ?\DateTimeInterface $expectedReceiptDate = null;

    // Closure tracking
    #[ORM\Column(type: 'boolean')]
    public bool $closed = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $closedReason = null;


    /**
     * Check if line is fully received
     */
    public function isFullyReceived(): bool
    {
        return $this->quantityReceived >= $this->quantityOrdered;
    }

    /**
     * Get remaining quantity to receive
     */
    public function getRemainingQuantity(): int
    {
        return max(0, $this->quantityOrdered - $this->quantityReceived);
    }
}
