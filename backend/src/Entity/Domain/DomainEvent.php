<?php

declare(strict_types=1);

namespace App\Entity\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'domain_event')]
#[ORM\Index(fields: ['aggregateId', 'version'])]
#[ORM\Index(fields: ['aggregateType', 'occurredAt'])]
class DomainEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public Uuid $id;

    #[ORM\Column(type: 'uuid')]
    public Uuid $aggregateId;

    #[ORM\Column(type: 'string', length: 100)]
    public string $aggregateType;

    #[ORM\Column(type: 'string', length: 100)]
    public string $event;

    #[ORM\Column(type: 'json')]
    public array $payload;

    #[ORM\Column(type: 'integer')]
    public int $version = 1;

    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $causedBy;

    #[ORM\Column]
    public readonly DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->occurredAt = new DateTimeImmutable();
    }
}
