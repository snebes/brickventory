<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Vendor Bill entity for accounts payable
 */
#[ORM\Entity]
#[ORM\Table(name: 'vendor_bill')]
#[ORM\Index(columns: ['vendor_id'], name: 'idx_bill_vendor')]
#[ORM\Index(columns: ['status'], name: 'idx_bill_status')]
#[ORM\Index(columns: ['due_date'], name: 'idx_bill_due_date')]
class VendorBill extends AbstractTransactionalEntity
{
    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $billNumber = '';

    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Vendor $vendor;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $vendorInvoiceNumber = null;

    #[ORM\Column(type: 'date', nullable: true)]
    public ?\DateTimeInterface $vendorInvoiceDate = null;

    #[ORM\Column(type: 'date')]
    #[Validate\NotNull]
    public \DateTimeInterface $billDate;

    #[ORM\Column(type: 'date')]
    #[Validate\NotNull]
    public \DateTimeInterface $dueDate;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $paymentTerms = null;

    #[ORM\ManyToOne(targetEntity: PurchaseOrder::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?PurchaseOrder $purchaseOrder = null;

    #[ORM\ManyToOne(targetEntity: ItemReceipt::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?ItemReceipt $itemReceipt = null;

    #[ORM\Column(type: 'string', length: 50)]
    public string $status = 'Open';

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    public ?string $currency = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6, nullable: true)]
    public ?float $exchangeRate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $subtotal = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $taxTotal = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $freightAmount = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $discountAmount = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $total = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $amountPaid = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $amountDue = 0.0;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $approvedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $approvedAt = null;

    /**
     * @var Collection<int, VendorBillLine>
     */
    #[ORM\OneToMany(targetEntity: VendorBillLine::class, mappedBy: 'vendorBill', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        parent::__construct();
        $this->billDate = new \DateTime();
        $this->dueDate = new \DateTime('+30 days');
        $this->lines = new ArrayCollection();
    }

    /**
     * Calculate and update financial totals from lines
     */
    public function calculateTotals(): void
    {
        $this->subtotal = 0.0;

        foreach ($this->lines as $line) {
            $this->subtotal += $line->amount;
        }

        $this->total = $this->subtotal + $this->taxTotal + $this->freightAmount - $this->discountAmount;
        $this->amountDue = $this->total - $this->amountPaid;
    }

    /**
     * Apply payment to this bill
     */
    public function applyPayment(float $amount, float $discount = 0.0): void
    {
        $this->amountPaid += $amount;
        $this->discountAmount += $discount;
        $this->amountDue = $this->total - $this->discountAmount - $this->amountPaid;

        // Update status
        if ($this->amountDue <= 0.001) {
            $this->status = 'Paid';
        } elseif ($this->amountPaid > 0) {
            $this->status = 'Partially Paid';
        }

        $this->touch();
    }
}
