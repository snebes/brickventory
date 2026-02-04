<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * InventoryTransferLine entity for line items in a transfer
 */
#[ORM\Entity]
#[ORM\Table(name: 'inventory_transfer_line')]
class InventoryTransferLine extends AbstractTransactionLineEntity
{
    #[ORM\ManyToOne(targetEntity: InventoryTransfer::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public InventoryTransfer $inventoryTransfer;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $fromBinLocation = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $toBinLocation = null;

    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $quantityRequested = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityShipped = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityReceived = 0;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $lotNumber = null;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $serialNumbers = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $unitCost = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $totalCost = 0.0;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    /**
     * Check if line is fully shipped
     */
    public function isFullyShipped(): bool
    {
        return $this->quantityShipped >= $this->quantityRequested;
    }

    /**
     * Check if line is fully received
     */
    public function isFullyReceived(): bool
    {
        return $this->quantityReceived >= $this->quantityShipped;
    }

    /**
     * Get remaining quantity to ship
     */
    public function getRemainingToShip(): int
    {
        return max(0, $this->quantityRequested - $this->quantityShipped);
    }

    /**
     * Get remaining quantity to receive
     */
    public function getRemainingToReceive(): int
    {
        return max(0, $this->quantityShipped - $this->quantityReceived);
    }

    /**
     * Record shipped quantity
     */
    public function recordShipped(int $quantity, float $unitCost): void
    {
        if ($quantity > $this->getRemainingToShip()) {
            throw new \InvalidArgumentException('Cannot ship more than requested quantity');
        }

        $this->quantityShipped += $quantity;
        $this->unitCost = $unitCost;
        $this->totalCost = $this->quantityShipped * $this->unitCost;
        $this->updatedAt = new \DateTime();
    }

    /**
     * Record received quantity
     */
    public function recordReceived(int $quantity): void
    {
        if ($quantity > $this->getRemainingToReceive()) {
            throw new \InvalidArgumentException('Cannot receive more than shipped quantity');
        }

        $this->quantityReceived += $quantity;
        $this->updatedAt = new \DateTime();
    }
}
