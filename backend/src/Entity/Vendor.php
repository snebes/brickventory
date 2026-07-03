<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Vendor entity for procure-to-pay workflow
 */
#[ORM\Entity]
#[ORM\Table(name: 'vendor')]
#[ORM\Index(columns: ['vendor_code'], name: 'idx_vendor_code')]
#[ORM\Index(columns: ['active'], name: 'idx_vendor_active')]
class Vendor extends AbstractMasterDataEntity
{
    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Validate\NotBlank]
    public string $vendorCode = '';

    #[ORM\Column(type: 'string', length: 255)]
    #[Validate\NotBlank]
    public string $vendorName = '';

    // Contact information
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Validate\Email]
    public ?string $email = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $phone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $website = null;

    // Address information (stored as JSON)
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $billingAddress = null;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $shippingAddress = null;

    // Payment terms and credit
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $defaultPaymentTerms = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    public ?string $defaultCurrency = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    public ?float $creditLimit = null;

    // Tax information
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $taxId = null;

    #[ORM\Column(type: 'boolean')]
    public bool $taxExempt = false;

    public function __construct()
    {
        parent::__construct();
    }
}
