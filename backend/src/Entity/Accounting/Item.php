<?php

declare(strict_types=1);

namespace App\Entity\Accounting;

use App\Entity\Domain\RecordsDomainEvents;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'accounting_item')]
class Item
{
    use RecordsDomainEvents;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public Uuid $id;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    public string $itemId = '';

    #[ORM\Column(type: 'string')]
    public string $displayName = '';

    #[ORM\Column(type: 'text')]
    public string $description = '';

    #[ORM\Column]
    public bool $isActive = true;

    #[ORM\Column]
    public readonly DateTimeImmutable $createdAt;

    #[ORM\Column]
    public private(set) DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
