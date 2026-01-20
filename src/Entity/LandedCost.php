<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Landed Cost entity for allocating additional costs (freight, duty, etc.) to received items
 */
#[ORM\Entity]
#[ORM\Table(name: 'landed_cost')]
#[ORM\Index(columns: ['item_receipt_id'], name: 'idx_landed_cost_receipt')]
#[ORM\Index(columns: ['vendor_bill_id'], name: 'idx_landed_cost_bill')]
class LandedCost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $landedCostNumber = '';

    #[ORM\ManyToOne(targetEntity: ItemReceipt::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public ItemReceipt $itemReceipt;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $vendorBillId = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $costCategory = 'Freight';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Validate\GreaterThan(0)]
    public float $totalCost = 0.0;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $allocationMethod = 'Value';

    #[ORM\Column(type: 'date')]
    #[Validate\NotNull]
    public \DateTimeInterface $appliedDate;

    /**
     * @var Collection<int, LandedCostAllocation>
     */
    #[ORM\OneToMany(targetEntity: LandedCostAllocation::class, mappedBy: 'landedCost', cascade: ['persist', 'remove'])]
    public Collection $allocations;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->appliedDate = new \DateTime();
        $this->allocations = new ArrayCollection();
    }
}
