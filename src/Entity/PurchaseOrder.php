<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
#[ORM\Table(name: 'purchase_order')]
#[ORM\Index(columns: ['vendor_id'], name: 'idx_po_vendor')]
#[ORM\Index(columns: ['status'], name: 'idx_po_status')]
class PurchaseOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $orderNumber = '';

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $orderDate;

    #[ORM\Column(type: 'string', length: 50)]
    public string $status = 'Pending Approval';

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $reference = null;

    // Vendor relationship
    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Vendor $vendor = null;

    // Dates
    #[ORM\Column(type: 'date', nullable: true)]
    public ?\DateTimeInterface $expectedReceiptDate = null;

    // Location
    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Location $shipToLocation = null;

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
        $this->uuid = Ulid::generate();
        $this->orderDate = new \DateTime();
        $this->lines = new ArrayCollection();
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
}
