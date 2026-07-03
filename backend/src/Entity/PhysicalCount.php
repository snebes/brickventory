<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Physical Count record for performing physical inventory counts.
 * Tracks the process of counting inventory and creating adjustments from variances.
 *
 * Status Workflow:
 * Planned -> In Progress -> Completed -> Adjustment Created
 * Any status can transition to Cancelled
 */
#[ORM\Entity]
#[ORM\Index(columns: ['status', 'transaction_date'])]
#[ORM\Index(columns: ['location_id', 'status'])]
class PhysicalCount extends AbstractTransactionalEntity
{
    // Count Type Constants
    public const TYPE_FULL_PHYSICAL = 'full_physical';
    public const TYPE_CYCLE_COUNT = 'cycle_count';
    public const TYPE_SPOT_COUNT = 'spot_count';

    public const VALID_TYPES = [
        self::TYPE_FULL_PHYSICAL,
        self::TYPE_CYCLE_COUNT,
        self::TYPE_SPOT_COUNT,
    ];

    // Status Constants
    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ADJUSTMENT_CREATED = 'adjustment_created';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_ADJUSTMENT_CREATED,
        self::STATUS_CANCELLED,
    ];

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Validate\NotBlank]
    public string $countNumber = '';

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_TYPES)]
    public string $countType = self::TYPE_FULL_PHYSICAL;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $locationId = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_STATUSES)]
    public string $status = self::STATUS_PLANNED;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $scheduledDate = null;

    #[ORM\Column(type: 'boolean')]
    public bool $freezeTransactions = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    /**
     * @var Collection<int, PhysicalCountLine>
     */
    #[ORM\OneToMany(targetEntity: PhysicalCountLine::class, mappedBy: 'physicalCount', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        parent::__construct();
        $this->lines = new ArrayCollection();
    }

    /**
     * Get the transaction number (count number).
     */
    public function getTransactionNumber(): string
    {
        return $this->countNumber;
    }

    /**
     * Get the transaction type identifier.
     */
    public function getTransactionType(): string
    {
        return 'physical_count';
    }

    /**
     * Get the count date (alias for transactionDate).
     */
    public function getCountDate(): \DateTimeInterface
    {
        return $this->transactionDate;
    }

    /**
     * Set the count date (alias for transactionDate).
     */
    public function setCountDate(\DateTimeInterface $date): self
    {
        $this->transactionDate = $date;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->status === self::STATUS_ADJUSTMENT_CREATED;
    }

    public function canCreateAdjustment(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function hasVariances(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->hasVariance()) {
                return true;
            }
        }
        return false;
    }
}
