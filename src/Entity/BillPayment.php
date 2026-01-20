<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Bill Payment entity for tracking payments made to vendors
 */
#[ORM\Entity]
#[ORM\Table(name: 'bill_payment')]
#[ORM\Index(columns: ['vendor_id'], name: 'idx_payment_vendor')]
#[ORM\Index(columns: ['status'], name: 'idx_payment_status')]
#[ORM\Index(columns: ['payment_date'], name: 'idx_payment_date')]
class BillPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $paymentNumber = '';

    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Vendor $vendor;

    #[ORM\Column(type: 'date')]
    #[Validate\NotNull]
    public \DateTimeInterface $paymentDate;

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
    public string $status = 'Draft';

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $updatedAt;

    /**
     * @var Collection<int, BillPaymentApplication>
     */
    #[ORM\OneToMany(targetEntity: BillPaymentApplication::class, mappedBy: 'billPayment', cascade: ['persist', 'remove'])]
    public Collection $applications;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->paymentDate = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->applications = new ArrayCollection();
    }
}
