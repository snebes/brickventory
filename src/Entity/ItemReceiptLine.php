<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
#[ORM\Table(name: 'item_receipt_line')]
class ItemReceiptLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\ManyToOne(targetEntity: ItemReceipt::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public ItemReceipt $itemReceipt;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\ManyToOne(targetEntity: PurchaseOrderLine::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public PurchaseOrderLine $purchaseOrderLine;

    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $quantityReceived;

    #[ORM\Column(type: 'integer')]
    public int $quantityAccepted = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityRejected = 0;

    /**
     * Cost per unit for this receipt line (from purchase order rate)
     * Used to create cost layers for FIFO accounting
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $unitCost = 0.0;

    // Warehouse location
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $binLocation = null;

    // Lot and serial tracking
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $lotNumber = null;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $serialNumbers = null;

    #[ORM\Column(type: 'date', nullable: true)]
    public ?\DateTimeInterface $expirationDate = null;

    // Notes
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $receivingNotes = null;

    // Link to cost layer
    #[ORM\ManyToOne(targetEntity: CostLayer::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?CostLayer $costLayer = null;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
    }
}
