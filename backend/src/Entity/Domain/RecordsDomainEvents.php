<?php

declare(strict_types=1);

namespace App\Entity\Domain;

trait RecordsDomainEvents
{
    /** @var list<array{type: string, payload: array}> */
    private array $pendingEvents = [];

    protected function recordEvent(string $type, array $payload): void
    {
        $this->pendingEvents[] = ['type' => $type, 'payload' => $payload];
    }

    public function popPendingEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];
        return $events;
    }
}
