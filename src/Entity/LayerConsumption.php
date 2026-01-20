<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Tracks consumption of cost layers during inventory transactions.
 * Provides traceability for which layers were consumed during adjustments,
 * fulfillments, and other inventory-decreasing transactions.
 */
#[ORM\Entity]
#[ORM\Index(columns: ['cost_layer_id', 'transaction_date'])]
#[ORM\Index(columns: ['transaction_type', 'transaction_id'])]
class LayerConsumption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\ManyToOne(targetEntity: CostLayer::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public CostLayer $costLayer;

    #[ORM\Column(type: 'string', length: 100)]
    #[Validate\NotBlank]
    public string $transactionType = '';

    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $transactionId = 0;

    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $quantityConsumed = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $unitCost = 0.0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $totalCost = 0.0;

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $transactionDate;

    #[ORM\ManyToOne(targetEntity: LayerConsumption::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?LayerConsumption $reversalOf = null;

    #[ORM\ManyToOne(targetEntity: LayerConsumption::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?LayerConsumption $reversedBy = null;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->transactionDate = new \DateTime();
    }
}
