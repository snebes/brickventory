<?php

declare(strict_types=1);

namespace App\Entity\Domain;

interface RecordableDomainEvent
{
    public const int VERSION = 1;

    public function eventType(): string;

    /** @return array<string, mixed> */
    public function toPayload(): array;
}
