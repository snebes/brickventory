<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Inventory Adjustment record for manually adjusting inventory quantities.
 * Modeled after NetSuite ERP inventory adjustments.
 */
#[ORM\Entity]
class InventoryAdjustment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Validate\NotBlank]
    public string $adjustmentNumber = '';

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $adjustmentDate;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $reason = '';

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $memo = null;

    #[ORM\Column(type: 'string', length: 50)]
    public string $status = 'approved';

    /**
     * @var Collection<int, InventoryAdjustmentLine>
     */
    #[ORM\OneToMany(targetEntity: InventoryAdjustmentLine::class, mappedBy: 'inventoryAdjustment', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->adjustmentDate = new \DateTime();
        $this->lines = new ArrayCollection();
    }
}
