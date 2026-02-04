<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Abstract base class for all transaction line entities.
 * Follows NetSuite ERP transaction line patterns.
 *
 * Transaction line entities include:
 * - Purchase Order Lines, Sales Order Lines
 * - Item Receipt Lines, Item Fulfillment Lines
 * - Inventory Adjustment Lines, Inventory Transfer Lines
 * - Vendor Bill Lines, Landed Cost Allocation Lines
 * - Physical Count Lines
 *
 * Standard fields:
 * - id: Auto-increment primary key
 * - uuid: ULID for external references
 * - lineNumber: Sequential line number within the transaction (NetSuite pattern)
 * - createdAt: Line creation timestamp
 * - updatedAt: Line last modification timestamp
 */
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractTransactionLineEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public string $uuid = '';

    /**
     * Sequential line number within the parent transaction.
     * Used for ordering and referencing specific lines (NetSuite pattern).
     */
    #[ORM\Column(type: 'integer')]
    public int $lineNumber = 1;

    /**
     * Line-level memo/notes.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $lineMemo = null;

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->uuid = (string) Ulid::generate();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Automatically update the updatedAt timestamp before update operations.
     */
    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Get the entity's unique identifier.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the entity's UUID.
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Get the line number.
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * Set the line number.
     */
    public function setLineNumber(int $lineNumber): self
    {
        $this->lineNumber = $lineNumber;
        return $this;
    }

    /**
     * Get the creation timestamp.
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Get the last update timestamp.
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }
}
