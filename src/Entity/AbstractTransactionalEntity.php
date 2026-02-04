<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Abstract base class for all transactional entities.
 * Follows NetSuite ERP transaction record patterns.
 *
 * Transactional entities include:
 * - Purchase Orders, Sales Orders
 * - Item Receipts, Item Fulfillments
 * - Inventory Adjustments, Inventory Transfers
 * - Vendor Bills, Bill Payments
 * - Landed Costs, Physical Counts
 *
 * Standard fields (NetSuite pattern):
 * - id: Auto-increment primary key (internal ID)
 * - uuid: ULID for external references
 * - externalId: External system identifier for integrations
 * - transactionDate: Primary transaction date
 * - memo: General notes/memo field
 * - postingPeriod: Accounting period (YYYY-MM format)
 * - createdAt: Record creation timestamp (auto-set)
 * - updatedAt: Last modification timestamp (auto-updated)
 * - createdBy: User who created the record
 * - modifiedBy: User who last modified the record
 * - isVoid: Whether transaction has been voided
 * - voidedAt: When transaction was voided
 * - voidedBy: User who voided the transaction
 */
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractTransactionalEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public string $uuid = '';

    /**
     * External system identifier for integrations (e.g., BrickLink order ID, Rebrickable set ID).
     * Used for syncing with external systems.
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $externalId = null;

    /**
     * Primary transaction date. Subclasses should map this to their specific date field
     * (orderDate, receiptDate, adjustmentDate, etc.) or override getTransactionDate().
     */
    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $transactionDate;

    /**
     * General memo/notes field for the transaction.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $memo = null;

    /**
     * Accounting/posting period in YYYY-MM format.
     * Used for period-based reporting and period close.
     */
    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    public ?string $postingPeriod = null;

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $updatedAt;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $createdBy = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $modifiedBy = null;

    /**
     * Whether this transaction has been voided.
     * Voiding preserves the record but nullifies its financial/inventory impact.
     */
    #[ORM\Column(type: 'boolean')]
    public bool $isVoid = false;

    /**
     * Timestamp when the transaction was voided.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $voidedAt = null;

    /**
     * User who voided the transaction.
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $voidedBy = null;

    /**
     * Reason for voiding the transaction.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $voidReason = null;

    public function __construct()
    {
        $this->uuid = (string) Ulid::generate();
        $this->transactionDate = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->postingPeriod = date('Y-m');
    }

    /**
     * Get the transaction number (e.g., PO-001, SO-001, IR-001).
     * Each subclass must implement this to return its specific transaction number.
     */
    abstract public function getTransactionNumber(): string;

    /**
     * Get the transaction type identifier (e.g., 'purchase_order', 'sales_order').
     * Used for searching across all transaction types.
     */
    abstract public function getTransactionType(): string;

    /**
     * Get the transaction date.
     * Subclasses may override this if they use a different date field.
     */
    public function getTransactionDate(): \DateTimeInterface
    {
        return $this->transactionDate;
    }

    /**
     * Void this transaction.
     */
    public function void(?string $reason = null, ?string $voidedBy = null): void
    {
        $this->isVoid = true;
        $this->voidedAt = new \DateTime();
        $this->voidedBy = $voidedBy;
        $this->voidReason = $reason;
    }

    /**
     * Check if the transaction is voided.
     */
    public function isVoided(): bool
    {
        return $this->isVoid;
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

    /**
     * Set the user who created this entity.
     */
    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Set the user who last modified this entity.
     */
    public function setModifiedBy(?string $modifiedBy): self
    {
        $this->modifiedBy = $modifiedBy;
        return $this;
    }

    /**
     * Manually update the updatedAt timestamp.
     * Useful for touching a record without other changes.
     */
    public function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
