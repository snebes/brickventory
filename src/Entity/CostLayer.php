<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

use App\Repository\CostLayerRepository;

/**
 * Represents a cost layer for FIFO inventory valuation.
 * Each time items are received at a specific cost, a new cost layer is created.
 * When items are fulfilled, the oldest cost layers are consumed first (FIFO).
 */
#[ORM\Entity(repositoryClass: CostLayerRepository::class)]
#[ORM\Index(columns: ['item_id', 'receipt_date'])]
class CostLayer
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

    #[ORM\ManyToOne(targetEntity: ItemReceiptLine::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?ItemReceiptLine $itemReceiptLine = null;

    /**
     * Original quantity received in this cost layer
     */
    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $quantityReceived = 0;

    /**
     * Remaining quantity in this cost layer (decreases as items are fulfilled)
     */
    #[ORM\Column(type: 'integer')]
    public int $quantityRemaining = 0;

    /**
     * Cost per unit for this cost layer (from purchase order rate)
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $unitCost = 0.0;

    /**
     * Date this cost layer was created (used for FIFO ordering)
     */
    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $receiptDate;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->receiptDate = new \DateTime();
    }

    /**
     * Calculate total cost of remaining inventory in this layer
     */
    public function getTotalCost(): float
    {
        return $this->quantityRemaining * $this->unitCost;
    }

    /**
     * Consume quantity from this cost layer and return the cost of consumed items
     * 
     * @param int $quantity Quantity to consume
     * @return array{consumed: int, cost: float} Array with consumed quantity and total cost
     */
    public function consume(int $quantity): array
    {
        $consumed = min($quantity, $this->quantityRemaining);
        $cost = $consumed * $this->unitCost;
        $this->quantityRemaining -= $consumed;
        
        return [
            'consumed' => $consumed,
            'cost' => $cost,
        ];
    }
}
