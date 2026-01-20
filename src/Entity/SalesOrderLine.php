<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Sales Order Line entity with quantity tracking for NetSuite workflow.
 */
#[ORM\Entity]
class SalesOrderLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public string $uuid = '';

    #[ORM\ManyToOne(targetEntity: SalesOrder::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public SalesOrder $salesOrder;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    /**
     * Total quantity ordered by the customer.
     */
    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $quantityOrdered = 0;

    /**
     * Quantity committed (soft allocated) from available inventory.
     * Formula: quantityCommitted <= min(quantityOrdered, item.quantityAvailable)
     */
    #[ORM\Column(type: 'integer')]
    public int $quantityCommitted = 0;

    /**
     * Quantity that has been physically shipped.
     */
    #[ORM\Column(type: 'integer')]
    public int $quantityFulfilled = 0;

    /**
     * Quantity that has been invoiced/billed.
     */
    #[ORM\Column(type: 'integer')]
    public int $quantityBilled = 0;

    /**
     * Location from which to fulfill this line (line-level override)
     */
    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Location $fulfillFromLocation = null;

    /**
     * Bin location from which to pick (optional)
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $pickFromBinLocation = null;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
    }

    /**
     * Get the quantity remaining to be fulfilled.
     */
    public function getQuantityRemaining(): int
    {
        return max(0, $this->quantityOrdered - $this->quantityFulfilled);
    }

    /**
     * Get the quantity remaining to be billed.
     */
    public function getQuantityRemainingToBill(): int
    {
        return max(0, $this->quantityFulfilled - $this->quantityBilled);
    }

    /**
     * Check if the line is fully fulfilled.
     */
    public function isFullyFulfilled(): bool
    {
        return $this->quantityFulfilled >= $this->quantityOrdered;
    }
}
