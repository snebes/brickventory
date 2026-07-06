<?php

declare(strict_types=1);

namespace App\Entity\Accounting\Event;

use App\Entity\Domain\RecordableDomainEvent;

final readonly class ItemCreated implements RecordableDomainEvent
{
    public const int VERSION = 1;

    public function __construct(
        public string $itemId,
        public string $displayName,
        public string $description = '',
    ) {}

    public function eventType(): string
    {
        return 'item.created';
    }

    public function toPayload(): array
    {
        return [
            'itemId' => $this->itemId,
            'displayName' => $this->displayName,
            'description' => $this->description,
        ];
    }
}
