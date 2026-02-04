<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Item Fulfillment Line entity for tracking line-level fulfillment details.
 *
 * Links fulfillment records to specific sales order lines and tracks
 * the quantity fulfilled from each line.
 */
#[ORM\Entity]
class ItemFulfillmentLine extends AbstractTransactionLineEntity
{
    #[ORM\ManyToOne(targetEntity: ItemFulfillment::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public ItemFulfillment $itemFulfillment;

    #[ORM\ManyToOne(targetEntity: SalesOrderLine::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public SalesOrderLine $salesOrderLine;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    /**
     * Quantity fulfilled in this fulfillment line.
     */
    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $quantityFulfilled = 0;

    /**
     * Optional serial numbers for items that track serial numbers.
     * Stored as JSON array.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $serialNumbers = null;

    /**
     * Optional lot number for items that track lot numbers.
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $lotNumber = null;

    /**
     * Optional bin/location within the warehouse.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $binLocation = null;
}
