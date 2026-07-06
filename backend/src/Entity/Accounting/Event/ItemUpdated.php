<?php

declare(strict_types=1);

namespace App\Entity\Accounting\Event;

use App\Entity\Domain\RecordableDomainEvent;

final readonly class ItemUpdated implements RecordableDomainEvent
{
    public const int VERSION = 1;

    public function __construct(
        public ?string $displayName = null,
        public ?string $description = null,
    ) {}

    public function eventType(): string
    {
        return 'item.updated';
    }

    public function toPayload(): array
    {
        return array_filter([
            'displayName' => $this->displayName,
            'description' => $this->description,
        ], fn (mixed $v): bool => $v !== null);
    }
}
