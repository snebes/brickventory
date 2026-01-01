<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
class SalesOrderLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\ManyToOne(targetEntity: SalesOrder::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public SalesOrder $salesOrder;

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\Column(type: 'integer')]
    #[Validate\GreaterThan(0)]
    public int $quantityOrdered = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityFulfilled = 0;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
    }
}
