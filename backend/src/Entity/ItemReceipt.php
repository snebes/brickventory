<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Item Receipt entity following NetSuite workflow pattern.
 * Created from a Purchase Order to record physical receipt of items.
 *
 * Status Progression: Pending → Received → Billed
 */
#[ORM\Entity]
#[ORM\Table(name: 'item_receipt')]
#[ORM\Index(columns: ['vendor_id'], name: 'idx_receipt_vendor')]
#[ORM\Index(columns: ['status'], name: 'idx_receipt_status')]
class ItemReceipt extends AbstractTransactionalEntity
{
    // Status constants following NetSuite workflow
    public const STATUS_PENDING = 'pending';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_BILLED = 'billed';

    // Allowed status values
    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RECEIVED,
        self::STATUS_BILLED,
    ];

    /**
     * Auto-generated receipt number for tracking.
     */
    #[ORM\Column(type: 'string', length: 55, unique: true)]
    public string $receiptNumber = '';

    #[ORM\ManyToOne(targetEntity: PurchaseOrder::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public PurchaseOrder $purchaseOrder;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_STATUSES)]
    public string $status = self::STATUS_RECEIVED;

    // Denormalized vendor for queries
    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Vendor $vendor = null;

    // Location - required for receiving inventory (inherits from PO by default)
    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Validate\NotNull(message: 'Receiving location is required.')]
    public Location $location;

    // Shipping information
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $vendorShipmentNumber = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $carrier = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $trackingNumber = null;

    // Freight and landed costs
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $freightCost = 0.0;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $landedCostCategory = null;

    // Inspection
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $inspectorId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $inspectionNotes = null;

    // Billing
    #[ORM\Column(type: 'boolean')]
    public bool $billImmediately = false;

    /**
     * @var Collection<int, ItemReceiptLine>
     */
    #[ORM\OneToMany(targetEntity: ItemReceiptLine::class, mappedBy: 'itemReceipt', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        parent::__construct();
        $this->receiptNumber = 'IR-' . date('YmdHis') . '-' . substr((string) microtime(true), -4);
        $this->lines = new ArrayCollection();
    }

    /**
     * Get the transaction number (receipt number).
     */
    public function getTransactionNumber(): string
    {
        return $this->receiptNumber;
    }

    /**
     * Get the transaction type identifier.
     */
    public function getTransactionType(): string
    {
        return 'item_receipt';
    }

    /**
     * Get the receipt date (alias for transactionDate).
     */
    public function getReceiptDate(): \DateTimeInterface
    {
        return $this->transactionDate;
    }

    /**
     * Set the receipt date (alias for transactionDate).
     */
    public function setReceiptDate(\DateTimeInterface $date): self
    {
        $this->transactionDate = $date;
        return $this;
    }

    /**
     * Get location ID for API access
     */
    public function getLocationId(): ?int
    {
        return $this->location?->id ?? null;
    }
}
