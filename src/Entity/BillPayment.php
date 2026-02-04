<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Bill Payment entity for tracking payments made to vendors.
 *
 * Status Progression: Draft → Posted / Void
 */
#[ORM\Entity]
#[ORM\Table(name: 'bill_payment')]
#[ORM\Index(columns: ['vendor_id'], name: 'idx_payment_vendor')]
#[ORM\Index(columns: ['status'], name: 'idx_payment_status')]
#[ORM\Index(columns: ['transaction_date'], name: 'idx_payment_date')]
class BillPayment extends AbstractTransactionalEntity
{
    // Status constants following NetSuite workflow
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_VOID = 'void';

    // Allowed status values
    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_POSTED,
        self::STATUS_VOID,
    ];

    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $paymentNumber = '';

    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Vendor $vendor;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $paymentMethod = 'Check';

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $checkNumber = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $bankAccountId = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    public ?string $currency = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6, nullable: true)]
    public ?float $exchangeRate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Validate\GreaterThan(0)]
    public float $totalAmount = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $discountTaken = 0.0;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_STATUSES)]
    public string $status = self::STATUS_DRAFT;

    /**
     * @var Collection<int, BillPaymentApplication>
     */
    #[ORM\OneToMany(targetEntity: BillPaymentApplication::class, mappedBy: 'billPayment', cascade: ['persist', 'remove'])]
    public Collection $applications;

    public function __construct()
    {
        parent::__construct();
        $this->applications = new ArrayCollection();
    }

    /**
     * Get the transaction number (payment number).
     */
    public function getTransactionNumber(): string
    {
        return $this->paymentNumber;
    }

    /**
     * Get the transaction type identifier.
     */
    public function getTransactionType(): string
    {
        return 'bill_payment';
    }

    /**
     * Get the payment date (alias for transactionDate).
     */
    public function getPaymentDate(): \DateTimeInterface
    {
        return $this->transactionDate;
    }

    /**
     * Set the payment date (alias for transactionDate).
     */
    public function setPaymentDate(\DateTimeInterface $date): self
    {
        $this->transactionDate = $date;
        return $this;
    }
}
