<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

use App\Repository\ItemEventRepository;

/**
 * Event sourcing entity that stores all item inventory events
 */
#[ORM\Entity(repositoryClass: ItemEventRepository::class)]
#[ORM\Index(columns: ['item_id', 'event_date'])]
class ItemEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Validate\NotNull]
    public Item $item;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $eventType = '';

    #[ORM\Column(type: 'integer')]
    public int $quantityChange = 0;

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $eventDate;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    public ?string $referenceType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $referenceId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $metadata = null;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->eventDate = new \DateTime();
    }
}
