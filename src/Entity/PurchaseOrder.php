<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
class PurchaseOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $orderNumber = '';

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $orderDate;

    #[ORM\Column(type: 'string', length: 50)]
    public string $status = 'pending';

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $notes = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $reference = null;

    /**
     * @var Collection<int, PurchaseOrderLine>
     */
    #[ORM\OneToMany(targetEntity: PurchaseOrderLine::class, mappedBy: 'purchaseOrder', cascade: ['persist', 'remove'])]
    public Collection $lines;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->orderDate = new \DateTime();
        $this->lines = new ArrayCollection();
    }
}
