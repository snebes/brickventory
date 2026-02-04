<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Vendor Bill Line entity - individual line items on a vendor bill
 */
#[ORM\Entity]
#[ORM\Table(name: 'vendor_bill_line')]
class VendorBillLine extends AbstractTransactionLineEntity
{
    #[ORM\ManyToOne(targetEntity: VendorBill::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public VendorBill $vendorBill;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $lineType = 'Item';

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Item $item = null;

    #[ORM\ManyToOne(targetEntity: ItemReceiptLine::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?ItemReceiptLine $receiptLine = null;

    #[ORM\ManyToOne(targetEntity: PurchaseOrderLine::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?PurchaseOrderLine $poLine = null;

    #[ORM\Column(type: 'string', length: 255)]
    public string $description = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $quantity = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $unitCost = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $amount = 0.0;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $expenseAccountId = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $varianceAmount = 0.0;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $varianceReason = null;


    /**
     * Calculate line amount
     */
    public function calculateAmount(): void
    {
        $this->amount = $this->quantity * $this->unitCost;
    }
}
