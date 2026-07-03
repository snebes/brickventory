<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Bin entity for warehouse bin/location management within a Location
 */
#[ORM\Entity]
#[ORM\Table(name: 'bin')]
#[ORM\Index(columns: ['location_id', 'bin_code'], name: 'idx_bin_location_code')]
#[ORM\Index(columns: ['location_id', 'active'], name: 'idx_bin_location_active')]
class Bin extends AbstractMasterDataEntity
{
    // Bin Type Constants
    public const TYPE_STORAGE = 'storage';
    public const TYPE_PICKING = 'picking';
    public const TYPE_RECEIVING = 'receiving';
    public const TYPE_SHIPPING = 'shipping';
    public const TYPE_QUARANTINE = 'quarantine';
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_RETURNS = 'returns';

    public const VALID_TYPES = [
        self::TYPE_STORAGE,
        self::TYPE_PICKING,
        self::TYPE_RECEIVING,
        self::TYPE_SHIPPING,
        self::TYPE_QUARANTINE,
        self::TYPE_DAMAGE,
        self::TYPE_RETURNS,
    ];

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Location $location;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $binCode = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $binName = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_TYPES)]
    public string $binType = self::TYPE_STORAGE;

    // Warehouse organization fields
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $zone = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $aisle = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $row = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $shelf = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $level = null;

    // Capacity management
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?float $capacity = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $currentUtilization = 0.0;

    // Inventory mixing rules
    #[ORM\Column(type: 'boolean')]
    public bool $allowMixedItems = true;

    #[ORM\Column(type: 'boolean')]
    public bool $allowMixedLots = true;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Check if bin can accept more inventory
     */
    public function canAcceptInventory(float $quantity = 0): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->capacity === null) {
            return true; // No capacity limit
        }

        return ($this->currentUtilization + $quantity) <= $this->capacity;
    }

    /**
     * Check if bin is empty
     */
    public function isEmpty(): bool
    {
        return $this->currentUtilization <= 0;
    }

    /**
     * Get utilization percentage
     */
    public function getUtilizationPercentage(): float
    {
        if ($this->capacity === null || $this->capacity <= 0) {
            return 0.0;
        }

        return ($this->currentUtilization / $this->capacity) * 100;
    }

    /**
     * Get full bin address
     */
    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->zone,
            $this->aisle,
            $this->row,
            $this->shelf,
            $this->level
        ]);

        return implode('-', $parts) ?: $this->binCode;
    }
}
