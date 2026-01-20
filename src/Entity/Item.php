<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
class Item extends AbstractMasterDataEntity
{
    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $itemId = '';

    #[ORM\Column(type: 'text')]
    public string $itemName = '';

    #[ORM\ManyToOne(targetEntity: ItemCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public ItemCategory $category;

    #[ORM\Column(type: 'integer')]
    public int $quantityAvailable = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityOnHand = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityOnOrder = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityBackOrdered = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityCommitted = 0;

    #[ORM\Column(type: 'string')]
    public string $elementIds = '';

    #[ORM\Column(type: 'string')]
    public string $partId = '';

    #[ORM\Column(type: 'string', length: 5)]
    public string $colorId = '';

    /**
     * Get total quantity on hand across all locations
     * @deprecated Use InventoryBalanceRepository->getTotalOnHand() instead
     */
    public function getTotalQuantityOnHand(): int
    {
        return $this->quantityOnHand;
    }

    /**
     * Get total available quantity across all locations
     * @deprecated Use InventoryBalanceRepository->getTotalAvailable() instead
     */
    public function getTotalQuantityAvailable(): int
    {
        return $this->quantityAvailable;
    }

    /**
     * Get total quantity on order across all locations
     * @deprecated Use InventoryBalanceRepository->getTotalOnOrder() instead
     */
    public function getTotalQuantityOnOrder(): int
    {
        return $this->quantityOnOrder;
    }

    public function __construct()
    {
        parent::__construct();
    }
}
