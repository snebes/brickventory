<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
class ItemFulfillment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\ManyToOne(targetEntity: SalesOrder::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public SalesOrder $salesOrder;

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $fulfillmentDate;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    #[ORM\Column(type: 'string', length: 50)]
    public string $status = 'fulfilled';

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->fulfillmentDate = new \DateTime();
    }
}
