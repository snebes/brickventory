<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Location entity for multi-location warehouse management.
 * Follows NetSuite ERP location management patterns.
 */
#[ORM\Entity]
#[ORM\Table(name: 'location')]
#[ORM\Index(columns: ['location_code'], name: 'idx_location_code')]
#[ORM\Index(columns: ['location_type', 'active'], name: 'idx_location_type_active')]
class Location
{
    // Location Type Constants
    public const TYPE_WAREHOUSE = 'warehouse';
    public const TYPE_STORE = 'store';
    public const TYPE_DISTRIBUTION_CENTER = 'distribution_center';
    public const TYPE_VIRTUAL = 'virtual';
    public const TYPE_VENDOR_LOCATION = 'vendor_location';

    public const VALID_TYPES = [
        self::TYPE_WAREHOUSE,
        self::TYPE_STORE,
        self::TYPE_DISTRIBUTION_CENTER,
        self::TYPE_VIRTUAL,
        self::TYPE_VENDOR_LOCATION,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public string $uuid = '';

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Validate\NotBlank]
    public string $locationCode = '';

    #[ORM\Column(type: 'string', length: 255)]
    #[Validate\NotBlank]
    public string $locationName = '';

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_TYPES)]
    public string $locationType = self::TYPE_WAREHOUSE;

    #[ORM\Column(type: 'boolean')]
    public bool $active = true;

    // Address information
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $address = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $timeZone = null;

    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    public ?string $country = null;

    // Operational settings
    #[ORM\Column(type: 'boolean')]
    public bool $useBinManagement = false;

    #[ORM\Column(type: 'boolean')]
    public bool $requiresBinOnReceipt = false;

    #[ORM\Column(type: 'boolean')]
    public bool $requiresBinOnFulfillment = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $defaultBinLocation = null;

    // Inventory settings
    #[ORM\Column(type: 'boolean')]
    public bool $allowNegativeInventory = false;

    #[ORM\Column(type: 'boolean')]
    public bool $isTransferSource = true;

    #[ORM\Column(type: 'boolean')]
    public bool $isTransferDestination = true;

    #[ORM\Column(type: 'boolean')]
    public bool $makeInventoryAvailable = true;

    // Manager and contact
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $managerId = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $contactPhone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $contactEmail = null;

    // Timestamps
    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Check if location can accept receipts
     */
    public function canReceiveInventory(): bool
    {
        return $this->active && $this->isTransferDestination;
    }

    /**
     * Check if location can fulfill orders
     */
    public function canFulfillOrders(): bool
    {
        return $this->active && $this->isTransferSource && $this->makeInventoryAvailable;
    }

    /**
     * Check if location requires bin management
     */
    public function requiresBinManagement(): bool
    {
        return $this->useBinManagement;
    }

    /**
     * Update the updatedAt timestamp
     */
    public function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
