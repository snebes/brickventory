<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

use App\Repository\OrderEventRepository;

/**
 * Event sourcing entity that stores all order-related events (Purchase Orders and Sales Orders)
 */
#[ORM\Entity(repositoryClass: OrderEventRepository::class)]
#[ORM\Index(columns: ['order_type', 'order_id', 'event_date'])]
class OrderEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $orderType = ''; // 'purchase_order' or 'sales_order'

    #[ORM\Column(type: 'integer')]
    public int $orderId;

    #[ORM\Column(type: 'string', length: 50)]
    #[Validate\NotBlank]
    public string $eventType = ''; // 'created', 'updated', 'deleted'

    #[ORM\Column(type: 'datetime')]
    #[Validate\NotNull]
    public \DateTimeInterface $eventDate;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $previousState = null; // JSON snapshot of previous state

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $newState = null; // JSON snapshot of new state

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $metadata = null; // Additional JSON metadata

    public function __construct()
    {
        $this->uuid = Ulid::generate();
        $this->eventDate = new \DateTime();
    }
}
