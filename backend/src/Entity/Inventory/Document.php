<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_document')]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 30)]
    public string $kind = '';

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    public string $number = '';

    #[ORM\Column(type: 'string', length: 30)]
    public string $status = '';

    #[ORM\Column(type: 'json')]
    public array $details = [];

    #[ORM\Column(type: 'json')]
    public array $items = [];

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    public ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    public ?Supplier $supplier = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
