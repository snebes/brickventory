<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * BinInventory entity for tracking inventory at bin level
 */
#[ORM\Entity]
#[ORM\Table(name: 'bin_inventory')]
#[ORM\Index(columns: ['item_id', 'location_id', 'bin_id'], name: 'idx_bin_inv_item_location_bin')]
#[ORM\Index(columns: ['bin_id'], name: 'idx_bin_inv_bin')]
#[ORM\UniqueConstraint(name: 'uniq_bin_inv', columns: ['item_id', 'location_id', 'bin_id', 'lot_number'])]
class BinInventory
{
    // Quality Status Constants
    public const QUALITY_AVAILABLE = 'available';
    public const QUALITY_QUARANTINE = 'quarantine';
    public const QUALITY_DAMAGED = 'damaged';
    public const QUALITY_EXPIRED = 'expired';

    public const VALID_QUALITY_STATUSES = [
        self::QUALITY_AVAILABLE,
        self::QUALITY_QUARANTINE,
        self::QUALITY_DAMAGED,
        self::QUALITY_EXPIRED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public string $uuid = '';

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Location $location;

    #[ORM\ManyToOne(targetEntity: Bin::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Bin $bin;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $lotNumber = null;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $serialNumbers = null;

    #[ORM\Column(type: 'date', nullable: true)]
    public ?\DateTimeInterface $expirationDate = null;

    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThanOrEqual(0)]
    public int $quantity = 0;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_QUALITY_STATUSES)]
    public string $qualityStatus = self::QUALITY_AVAILABLE;

    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $lastMovementDate = null;

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
     * Check if this bin inventory is available for picking
     */
    public function isAvailable(): bool
    {
        return $this->qualityStatus === self::QUALITY_AVAILABLE && $this->quantity > 0;
    }

    /**
     * Check if this inventory is expired
     */
    public function isExpired(): bool
    {
        if ($this->expirationDate === null) {
            return false;
        }

        return $this->expirationDate < new \DateTime();
    }

    /**
     * Add quantity to bin
     */
    public function addQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        $this->quantity += $quantity;
        $this->lastMovementDate = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Remove quantity from bin
     */
    public function removeQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        if ($quantity > $this->quantity) {
            throw new \InvalidArgumentException('Cannot remove more than available quantity');
        }

        $this->quantity -= $quantity;
        $this->lastMovementDate = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Update the updatedAt timestamp
     */
    public function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
