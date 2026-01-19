<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Item Fulfillment entity for tracking physical shipments of sales orders.
 * 
 * Status Progression: Picked → Packed → Shipped → Delivered
 */
#[ORM\Entity]
class ItemFulfillment
{
    // Status constants following NetSuite fulfillment workflow
    public const STATUS_PICKED = 'picked';
    public const STATUS_PACKED = 'packed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';

    // Allowed status values
    public const VALID_STATUSES = [
        self::STATUS_PICKED,
        self::STATUS_PACKED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    /**
     * Auto-generated fulfillment number for tracking.
     */
    #[ORM\Column(type: 'string', length: 55, unique: true)]
    public string $fulfillmentNumber = '';

    #[ORM\ManyToOne(targetEntity: SalesOrder::class, inversedBy: 'fulfillments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public SalesOrder $salesOrder;

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $fulfillmentDate;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_STATUSES)]
    public string $status = self::STATUS_PICKED;

    /**
     * Shipping carrier/method (e.g., "FedEx Ground", "UPS Next Day").
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $shipMethod = null;

    /**
     * Tracking number provided by carrier.
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $trackingNumber = null;

    /**
     * Actual shipping cost incurred.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    public ?string $shippingCost = null;

    /**
     * Timestamp when the fulfillment was shipped.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $shippedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    /**
     * @var Collection<int, ItemFulfillmentLine>
     */
    #[ORM\OneToMany(targetEntity: ItemFulfillmentLine::class, mappedBy: 'itemFulfillment', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->fulfillmentNumber = 'IF-' . date('YmdHis') . '-' . substr((string) microtime(true), -4);
        $this->fulfillmentDate = new \DateTime();
        $this->lines = new ArrayCollection();
    }

    /**
     * Check if the fulfillment has been shipped.
     */
    public function isShipped(): bool
    {
        return in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_DELIVERED], true);
    }

    /**
     * Mark the fulfillment as shipped.
     */
    public function markAsShipped(?string $trackingNumber = null, ?string $shipMethod = null): void
    {
        $this->status = self::STATUS_SHIPPED;
        $this->shippedAt = new \DateTime();
        
        if ($trackingNumber !== null) {
            $this->trackingNumber = $trackingNumber;
        }
        if ($shipMethod !== null) {
            $this->shipMethod = $shipMethod;
        }
    }
}
