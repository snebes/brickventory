<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Bill Payment Application entity - tracks which bills a payment is applied to
 */
#[ORM\Entity]
#[ORM\Table(name: 'bill_payment_application')]
#[ORM\Index(columns: ['bill_payment_id'], name: 'idx_app_payment')]
#[ORM\Index(columns: ['vendor_bill_id'], name: 'idx_app_bill')]
class BillPaymentApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\ManyToOne(targetEntity: BillPayment::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public BillPayment $billPayment;

    #[ORM\ManyToOne(targetEntity: VendorBill::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public VendorBill $vendorBill;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Validate\GreaterThan(0)]
    public float $amountApplied = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $discountApplied = 0.0;

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $appliedAt;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->appliedAt = new \DateTime();
    }
}
