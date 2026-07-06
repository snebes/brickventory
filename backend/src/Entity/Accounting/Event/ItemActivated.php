<?php

declare(strict_types=1);

namespace App\Entity\Accounting\Event;

use App\Entity\Domain\RecordableDomainEvent;

final readonly class ItemActivated implements RecordableDomainEvent
{
    public const int VERSION = 1;

    public function eventType(): string
    {
        return 'item.activated';
    }

    public function toPayload(): array
    {
        return [];
    }
}
