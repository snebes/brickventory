<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Abstract base class for all master data entities.
 * Follows NetSuite ERP master data record patterns.
 * 
 * Master data entities include:
 * - Items, Item Categories
 * - Vendors, Locations, Bins
 * - Customers, Employees (future)
 * 
 * Standard fields:
 * - id: Auto-increment primary key
 * - uuid: ULID for external references
 * - createdAt: Record creation timestamp (auto-set)
 * - updatedAt: Last modification timestamp (auto-updated)
 * - createdBy: User who created the record
 * - modifiedBy: User who last modified the record
 * - active: Active/inactive flag for soft deletion
 */
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractMasterDataEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public string $uuid = '';

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $updatedAt;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $createdBy = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $modifiedBy = null;

    #[ORM\Column(type: 'boolean')]
    public bool $active = true;

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
     * Check if the entity is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Activate the entity.
     */
    public function activate(): self
    {
        $this->active = true;
        $this->touch();
        return $this;
    }

    /**
     * Deactivate the entity.
     */
    public function deactivate(): self
    {
        $this->active = false;
        $this->touch();
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
