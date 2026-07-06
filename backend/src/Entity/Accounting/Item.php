<?php

declare(strict_types=1);

namespace App\Entity\Accounting;

use App\Entity\Accounting\Event\ItemActivated;
use App\Entity\Accounting\Event\ItemCreated;
use App\Entity\Accounting\Event\ItemDeactivated;
use App\Entity\Accounting\Event\ItemUpdated;
use App\Entity\Domain\RecordableDomainEvent;
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
    public readonly Uuid $id;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    public private(set) string $itemId = '';

    #[ORM\Column(type: 'string')]
    public private(set) string $displayName = '';

    #[ORM\Column(type: 'text')]
    public private(set) string $description = '';

    #[ORM\Column]
    public private(set) bool $isActive = true;

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

    /**
     * Single entry point for all state mutations.
     * Applies the change, captures old/new values, then records the domain event.
     */
    public function apply(RecordableDomainEvent $event): void
    {
        $payload = match (true) {
            $event instanceof ItemCreated     => $this->whenCreated($event),
            $event instanceof ItemUpdated     => $this->whenUpdated($event),
            $event instanceof ItemActivated   => $this->whenActivated(),
            $event instanceof ItemDeactivated => $this->whenDeactivated(),
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported event %s for %s', $event::class, self::class),
            ),
        };

        $this->updatedAt = new DateTimeImmutable();
        $this->recordEvent($event->eventType(), $event::VERSION, $payload);
    }

    private function whenCreated(ItemCreated $event): array
    {
        $this->itemId      = $event->itemId;
        $this->displayName = $event->displayName;
        $this->description = $event->description;

        return $event->toPayload();
    }

    private function whenUpdated(ItemUpdated $event): array
    {
        $changes = [];

        if ($event->displayName !== null && $event->displayName !== $this->displayName) {
            $changes['displayName'] = ['old' => $this->displayName, 'new' => $event->displayName];
            $this->displayName = $event->displayName;
        }

        if ($event->description !== null && $event->description !== $this->description) {
            $changes['description'] = ['old' => $this->description, 'new' => $event->description];
            $this->description = $event->description;
        }

        return ['changes' => $changes];
    }

    private function whenActivated(): array
    {
        $was = $this->isActive;
        $this->isActive = true;

        return ['isActive' => ['old' => $was, 'new' => true]];
    }

    private function whenDeactivated(): array
    {
        $was = $this->isActive;
        $this->isActive = false;

        return ['isActive' => ['old' => $was, 'new' => false]];
    }
}
