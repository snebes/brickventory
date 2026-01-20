<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * InventoryBalance entity for location-specific inventory tracking.
 * Replaces item-level quantity fields with location-specific tracking.
 */
#[ORM\Entity]
#[ORM\Table(name: 'inventory_balance')]
#[ORM\UniqueConstraint(name: 'uniq_item_location_bin', columns: ['item_id', 'location_id', 'bin_location'])]
#[ORM\Index(columns: ['item_id', 'location_id'], name: 'idx_inventory_balance_item_location')]
#[ORM\Index(columns: ['location_id'], name: 'idx_inventory_balance_location')]
class InventoryBalance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Location $location;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $binLocation = null;

    // Quantity tracking
    #[ORM\Column(type: 'integer')]
    public int $quantityOnHand = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityAvailable = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityCommitted = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityOnOrder = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityInTransit = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityReserved = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityBackordered = 0;

    // Costing
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $averageCost = 0.0;

    // Tracking dates
    #[ORM\Column(type: 'date', nullable: true)]
    public ?\DateTimeInterface $lastCountDate = null;

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
     * Update quantity on hand and recalculate available
     */
    public function updateQuantityOnHand(int $delta): void
    {
        $this->quantityOnHand += $delta;
        $this->recalculateAvailable();
        $this->touch();
    }

    /**
     * Update quantity committed and recalculate available
     */
    public function updateQuantityCommitted(int $delta): void
    {
        $this->quantityCommitted += $delta;
        $this->recalculateAvailable();
        $this->touch();
    }

    /**
     * Update quantity on order
     */
    public function updateQuantityOnOrder(int $delta): void
    {
        $this->quantityOnOrder += $delta;
        $this->touch();
    }

    /**
     * Update quantity in transit
     */
    public function updateQuantityInTransit(int $delta): void
    {
        $this->quantityInTransit += $delta;
        $this->touch();
    }

    /**
     * Update quantity reserved and recalculate available
     */
    public function updateQuantityReserved(int $delta): void
    {
        $this->quantityReserved += $delta;
        $this->recalculateAvailable();
        $this->touch();
    }

    /**
     * Recalculate available quantity
     * Available = OnHand - Committed - Reserved
     */
    public function recalculateAvailable(): void
    {
        $this->quantityAvailable = $this->quantityOnHand - $this->quantityCommitted - $this->quantityReserved;
    }

    /**
     * Check if there is sufficient available inventory
     */
    public function hasAvailableQuantity(int $quantity): bool
    {
        return $this->quantityAvailable >= $quantity;
    }

    /**
     * Mark last movement
     */
    public function markMovement(): void
    {
        $this->lastMovementDate = new \DateTime();
        $this->touch();
    }

    /**
     * Update the updatedAt timestamp
     */
    public function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Get total inventory value at this location/bin
     */
    public function getTotalValue(): float
    {
        return $this->quantityOnHand * $this->averageCost;
    }
}
