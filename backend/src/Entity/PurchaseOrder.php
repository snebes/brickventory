<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Purchase Order entity following NetSuite ERP workflow pattern.
 *
 * Status Progression: Pending Approval → Pending Receipt → Partially Received → Received → Closed/Cancelled
 */
#[ORM\Entity(repositoryClass: \App\Repository\PurchaseOrderRepository::class)]
#[ORM\Table(name: 'purchase_order')]
#[ORM\Index(columns: ['vendor_id'], name: 'idx_po_vendor')]
#[ORM\Index(columns: ['status'], name: 'idx_po_status')]
class PurchaseOrder extends AbstractTransactionalEntity
{
    // Status constants following NetSuite workflow
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_PENDING_RECEIPT = 'pending_receipt';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    // Allowed status values
    public const VALID_STATUSES = [
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_PENDING_RECEIPT,
        self::STATUS_PARTIALLY_RECEIVED,
        self::STATUS_RECEIVED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $orderNumber = '';

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_STATUSES)]
    public string $status = self::STATUS_PENDING_APPROVAL;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $reference = null;

    // Vendor relationship - required per NetSuite ERP model
    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Validate\NotNull(message: 'Vendor is required. Please select a vendor before saving the Purchase Order.')]
    public Vendor $vendor;

    // Dates
    #[ORM\Column(type: 'date', nullable: true)]
    public ?\DateTimeInterface $expectedReceiptDate = null;

    // Location - required for receiving inventory (NetSuite pattern)
    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Validate\NotNull(message: 'Receiving location is required. Please select a location before saving the Purchase Order.')]
    public Location $location;

    // Address
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $billToAddress = null;

    // Shipping and payment
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $shippingMethod = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $paymentTerms = null;

    // Currency
    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    public ?string $currency = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6, nullable: true)]
    public ?float $exchangeRate = null;

    // Financial totals
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $subtotal = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $taxTotal = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $shippingCost = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $total = 0.0;

    // Buyer and approver
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $buyerId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $departmentId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $approvedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $approvedAt = null;

    /**
     * @var Collection<int, PurchaseOrderLine>
     */
    #[ORM\OneToMany(targetEntity: PurchaseOrderLine::class, mappedBy: 'purchaseOrder', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        parent::__construct();
        $this->lines = new ArrayCollection();
    }

    /**
     * Get the transaction number (PO number).
     */
    public function getTransactionNumber(): string
    {
        return $this->orderNumber;
    }

    /**
     * Get the transaction type identifier.
     */
    public function getTransactionType(): string
    {
        return 'purchase_order';
    }

    /**
     * Get the order date (alias for transactionDate).
     */
    public function getOrderDate(): \DateTimeInterface
    {
        return $this->transactionDate;
    }

    /**
     * Set the order date (alias for transactionDate).
     */
    public function setOrderDate(\DateTimeInterface $date): self
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

    /**
     * Calculate and update financial totals from lines
     */
    public function calculateTotals(): void
    {
        $this->subtotal = 0.0;
        $this->taxTotal = 0.0;

        foreach ($this->lines as $line) {
            $lineAmount = $line->quantityOrdered * $line->rate;
            $this->subtotal += $lineAmount;
            $this->taxTotal += $line->taxAmount ?? 0.0;
        }

        $this->total = $this->subtotal + $this->taxTotal + $this->shippingCost;
    }

    /**
     * Check if the PO can be received.
     */
    public function canBeReceived(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_RECEIPT,
            self::STATUS_PARTIALLY_RECEIVED,
        ], true);
    }

    /**
     * Update the status based on receipt state.
     */
    public function updateReceiptStatus(): void
    {
        if ($this->isFullyReceived()) {
            $this->status = self::STATUS_RECEIVED;
        } elseif ($this->isPartiallyReceived()) {
            $this->status = self::STATUS_PARTIALLY_RECEIVED;
        }
    }

    /**
     * Check if all lines are fully received.
     */
    public function isFullyReceived(): bool
    {
        foreach ($this->lines as $line) {
            if (!$line->isFullyReceived()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if any lines are partially received.
     */
    public function isPartiallyReceived(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->quantityReceived > 0) {
                return true;
            }
        }
        return false;
    }
}
