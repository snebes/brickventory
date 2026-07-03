<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Landed Cost entity for allocating additional costs (freight, duty, etc.) to received items.
 *
 * Status Progression: Draft → Posted
 */
#[ORM\Entity]
#[ORM\Table(name: 'landed_cost')]
#[ORM\Index(columns: ['item_receipt_id'], name: 'idx_landed_cost_receipt')]
#[ORM\Index(columns: ['vendor_bill_id'], name: 'idx_landed_cost_bill')]
class LandedCost extends AbstractTransactionalEntity
{
    // Status constants following NetSuite workflow
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';

    // Allowed status values
    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_POSTED,
    ];

    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $landedCostNumber = '';

    #[ORM\ManyToOne(targetEntity: ItemReceipt::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public ItemReceipt $itemReceipt;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $vendorBillId = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $costCategory = 'Freight';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Validate\GreaterThan(0)]
    public float $totalCost = 0.0;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $allocationMethod = 'Value';

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_STATUSES)]
    public string $status = self::STATUS_DRAFT;

    /**
     * @var Collection<int, LandedCostAllocation>
     */
    #[ORM\OneToMany(targetEntity: LandedCostAllocation::class, mappedBy: 'landedCost', cascade: ['persist', 'remove'])]
    public Collection $allocations;

    public function __construct()
    {
        parent::__construct();
        $this->allocations = new ArrayCollection();
    }

    /**
     * Get the transaction number (landed cost number).
     */
    public function getTransactionNumber(): string
    {
        return $this->landedCostNumber;
    }

    /**
     * Get the transaction type identifier.
     */
    public function getTransactionType(): string
    {
        return 'landed_cost';
    }

    /**
     * Get the applied date (alias for transactionDate).
     */
    public function getAppliedDate(): \DateTimeInterface
    {
        return $this->transactionDate;
    }

    /**
     * Set the applied date (alias for transactionDate).
     */
    public function setAppliedDate(\DateTimeInterface $date): self
    {
        $this->transactionDate = $date;
        return $this;
    }
}
