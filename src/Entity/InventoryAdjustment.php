<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Inventory Adjustment record for manually adjusting inventory quantities.
 * Modeled after NetSuite ERP inventory adjustments.
 * 
 * Status Workflow:
 * Draft -> Pending Approval -> Approved -> Posted
 * Any status can transition to Void
 */
#[ORM\Entity]
#[ORM\Index(columns: ['status', 'adjustment_date'])]
#[ORM\Index(columns: ['adjustment_type', 'status'])]
class InventoryAdjustment extends AbstractTransactionalEntity
{
    // Adjustment Type Constants
    public const TYPE_QUANTITY_ADJUSTMENT = 'quantity_adjustment';
    public const TYPE_COST_REVALUATION = 'cost_revaluation';
    public const TYPE_PHYSICAL_COUNT = 'physical_count';
    public const TYPE_CYCLE_COUNT = 'cycle_count';
    public const TYPE_WRITE_DOWN = 'write_down';
    public const TYPE_WRITE_OFF = 'write_off';
    public const TYPE_ASSEMBLY = 'assembly';
    public const TYPE_DISASSEMBLY = 'disassembly';

    public const VALID_TYPES = [
        self::TYPE_QUANTITY_ADJUSTMENT,
        self::TYPE_COST_REVALUATION,
        self::TYPE_PHYSICAL_COUNT,
        self::TYPE_CYCLE_COUNT,
        self::TYPE_WRITE_DOWN,
        self::TYPE_WRITE_OFF,
        self::TYPE_ASSEMBLY,
        self::TYPE_DISASSEMBLY,
    ];

    // Status Constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';
    public const STATUS_VOID = 'void';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_POSTED,
        self::STATUS_VOID,
    ];

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Validate\NotBlank]
    public string $adjustmentNumber = '';

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $adjustmentDate;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    #[Validate\Choice(choices: self::VALID_TYPES)]
    public string $adjustmentType = self::TYPE_QUANTITY_ADJUSTMENT;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $reason = '';

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $memo = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_STATUSES)]
    public string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    public ?string $postingPeriod = null;

    /**
     * Location for the adjustment - required field following NetSuite ERP pattern.
     * In NetSuite, inventory adjustments must specify a location on the header
     * to determine where inventory quantities are adjusted.
     */
    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull(message: 'Location is required for inventory adjustments')]
    public Location $location;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $totalQuantityChange = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $totalValueChange = 0.0;

    #[ORM\Column(type: 'boolean')]
    public bool $approvalRequired = false;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $approvedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $postedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $postedAt = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $referenceNumber = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $countDate = null;

    /**
     * @var Collection<int, InventoryAdjustmentLine>
     */
    #[ORM\OneToMany(targetEntity: InventoryAdjustmentLine::class, mappedBy: 'inventoryAdjustment', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        parent::__construct();
        $this->adjustmentDate = new \DateTime();
        $this->lines = new ArrayCollection();
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBePosted(): bool
    {
        // Can post if approved, OR if draft and approval is not required
        return ($this->status === self::STATUS_APPROVED && !$this->isPosted()) ||
               ($this->status === self::STATUS_DRAFT && !$this->approvalRequired && !$this->isPosted());
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
