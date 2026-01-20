<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

/**
 * Sales Order entity following NetSuite workflow pattern.
 * 
 * Status Progression: Pending Approval → Pending Fulfillment → Partially Fulfilled → Fulfilled → Billed → Closed/Cancelled
 */
#[ORM\Entity(repositoryClass: \App\Repository\SalesOrderRepository::class)]
class SalesOrder extends AbstractTransactionalEntity
{
    // Status constants following NetSuite workflow
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_PENDING_FULFILLMENT = 'pending_fulfillment';
    public const STATUS_PARTIALLY_FULFILLED = 'partially_fulfilled';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_BILLED = 'billed';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    // Allowed status values
    public const VALID_STATUSES = [
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_PENDING_FULFILLMENT,
        self::STATUS_PARTIALLY_FULFILLED,
        self::STATUS_FULFILLED,
        self::STATUS_BILLED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $orderNumber = '';

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $orderDate;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\Choice(choices: self::VALID_STATUSES)]
    public string $status = self::STATUS_PENDING_FULFILLMENT;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    /**
     * Default location from which to fulfill this order
     */
    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Location $fulfillFromLocation = null;

    /**
     * @var Collection<int, SalesOrderLine>
     */
    #[ORM\OneToMany(targetEntity: SalesOrderLine::class, mappedBy: 'salesOrder', cascade: ['persist', 'remove'])]
    public Collection $lines;

    /**
     * @var Collection<int, ItemFulfillment>
     */
    #[ORM\OneToMany(targetEntity: ItemFulfillment::class, mappedBy: 'salesOrder', cascade: ['persist', 'remove'])]
    public Collection $fulfillments;

    public function __construct()
    {
        parent::__construct();
        $this->orderDate = new \DateTime();
        $this->lines = new ArrayCollection();
        $this->fulfillments = new ArrayCollection();
    }

    /**
     * Check if the order can be fulfilled (has items pending fulfillment).
     */
    public function canBeFulfilled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_FULFILLMENT,
            self::STATUS_PARTIALLY_FULFILLED,
        ], true);
    }

    /**
     * Check if the order is fully fulfilled.
     */
    public function isFullyFulfilled(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->quantityFulfilled < $line->quantityOrdered) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if the order is partially fulfilled.
     */
    public function isPartiallyFulfilled(): bool
    {
        $hasFulfilled = false;
        $hasUnfulfilled = false;

        foreach ($this->lines as $line) {
            if ($line->quantityFulfilled > 0) {
                $hasFulfilled = true;
            }
            if ($line->quantityFulfilled < $line->quantityOrdered) {
                $hasUnfulfilled = true;
            }
        }

        return $hasFulfilled && $hasUnfulfilled;
    }

    /**
     * Update the order status based on fulfillment state.
     */
    public function updateFulfillmentStatus(): void
    {
        if ($this->isFullyFulfilled()) {
            $this->status = self::STATUS_FULFILLED;
        } elseif ($this->isPartiallyFulfilled()) {
            $this->status = self::STATUS_PARTIALLY_FULFILLED;
        }
    }
}
