<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
#[ORM\Table(name: 'item_receipt')]
#[ORM\Index(columns: ['vendor_id'], name: 'idx_receipt_vendor')]
#[ORM\Index(columns: ['status'], name: 'idx_receipt_status')]
class ItemReceipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public string $uuid = '';

    #[ORM\ManyToOne(targetEntity: PurchaseOrder::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public PurchaseOrder $purchaseOrder;

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $receiptDate;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    #[ORM\Column(type: 'string', length: 50)]
    public string $status = 'Received';

    // Denormalized vendor for queries
    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Vendor $vendor = null;

    // Location - required for receiving inventory (inherits from PO by default)
    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Validate\NotNull(message: 'Receiving location is required.')]
    public Location $location;

    // Shipping information
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $vendorShipmentNumber = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $carrier = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $trackingNumber = null;

    // Freight and landed costs
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $freightCost = 0.0;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $landedCostCategory = null;

    // Inspection
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $inspectorId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $inspectionNotes = null;

    // Billing
    #[ORM\Column(type: 'boolean')]
    public bool $billImmediately = false;

    /**
     * @var Collection<int, ItemReceiptLine>
     */
    #[ORM\OneToMany(targetEntity: ItemReceiptLine::class, mappedBy: 'itemReceipt', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->receiptDate = new \DateTime();
        $this->lines = new ArrayCollection();
    }

    /**
     * Get location ID for API access
     */
    public function getLocationId(): ?int
    {
        return $this->location?->id ?? null;
    }
}
