<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * InventoryTransfer entity for moving inventory between locations
 *
 * Status Progression: Pending → In Transit → Partially Received → Received / Cancelled
 */
#[ORM\Entity]
#[ORM\Table(name: 'inventory_transfer')]
#[ORM\Index(columns: ['from_location_id', 'to_location_id', 'status'], name: 'idx_transfer_locations_status')]
#[ORM\Index(columns: ['status', 'transaction_date'], name: 'idx_transfer_status_date')]
class InventoryTransfer extends AbstractTransactionalEntity
{
    // Status Constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_TRANSIT,
        self::STATUS_PARTIALLY_RECEIVED,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    // Transfer Type Constants
    public const TYPE_STANDARD = 'standard';
    public const TYPE_EMERGENCY = 'emergency';
    public const TYPE_REPLENISHMENT = 'replenishment';
    public const TYPE_RETURN = 'return';

    public const VALID_TYPES = [
        self::TYPE_STANDARD,
        self::TYPE_EMERGENCY,
        self::TYPE_REPLENISHMENT,
        self::TYPE_RETURN,
    ];

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    public string $transferNumber = '';

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Location $fromLocation;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Location $toLocation;

    #[ORM\Column(type: 'date', nullable: true)]
    public ?\DateTimeInterface $expectedDeliveryDate = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_STATUSES)]
    public string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_TYPES)]
    public string $transferType = self::TYPE_STANDARD;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $carrier = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $trackingNumber = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $shippingCost = null;

    // User tracking
    #[ORM\Column(type: 'string', length: 255)]
    public string $requestedBy = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $approvedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $shippedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $shippedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $receivedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $receivedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    /**
     * @var Collection<int, InventoryTransferLine>
     */
    #[ORM\OneToMany(targetEntity: InventoryTransferLine::class, mappedBy: 'inventoryTransfer', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        parent::__construct();
        $this->transferNumber = 'XFER-' . date('Ymd') . '-' . substr((string) microtime(true), -6);
        $this->lines = new ArrayCollection();
    }

    /**
     * Get the transaction number (transfer number).
     */
    public function getTransactionNumber(): string
    {
        return $this->transferNumber;
    }

    /**
     * Get the transaction type identifier.
     */
    public function getTransactionType(): string
    {
        return 'inventory_transfer';
    }

    /**
     * Get the transfer date (alias for transactionDate).
     */
    public function getTransferDate(): \DateTimeInterface
    {
        return $this->transactionDate;
    }

    /**
     * Set the transfer date (alias for transactionDate).
     */
    public function setTransferDate(\DateTimeInterface $date): self
    {
        $this->transactionDate = $date;
        return $this;
    }

    /**
     * Check if transfer is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transfer is in transit
     */
    public function isInTransit(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    /**
     * Check if transfer is complete
     */
    public function isComplete(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /**
     * Check if transfer is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Mark as shipped
     */
    public function markAsShipped(string $shippedBy): void
    {
        $this->status = self::STATUS_IN_TRANSIT;
        $this->shippedBy = $shippedBy;
        $this->shippedAt = new \DateTime();
        $this->touch();
    }

    /**
     * Mark as received
     */
    public function markAsReceived(string $receivedBy): void
    {
        $this->status = self::STATUS_RECEIVED;
        $this->receivedBy = $receivedBy;
        $this->receivedAt = new \DateTime();
        $this->touch();
    }

    /**
     * Approve transfer
     */
    public function approve(string $approvedBy): void
    {
        $this->approvedBy = $approvedBy;
        $this->approvedAt = new \DateTime();
        $this->touch();
    }

    /**
     * Cancel transfer
     */
    public function cancel(): void
    {
        if ($this->status === self::STATUS_IN_TRANSIT) {
            throw new \LogicException('Cannot cancel transfer that is already in transit');
        }
        if ($this->status === self::STATUS_RECEIVED) {
            throw new \LogicException('Cannot cancel transfer that has been received');
        }

        $this->status = self::STATUS_CANCELLED;
        $this->touch();
    }

    /**
     * Get total cost of transfer (sum of all line items)
     */
    public function getTotalCost(): float
    {
        $total = 0.0;
        foreach ($this->lines as $line) {
            $total += $line->totalCost;
        }
        return $total;
    }
}
