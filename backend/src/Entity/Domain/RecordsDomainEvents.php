<?php

declare(strict_types=1);

namespace App\Entity\Domain;

trait RecordsDomainEvents
{
    /** @var list<array{type: string, version: int, payload: array}> */
    private array $pendingEvents = [];

    protected function recordEvent(string $type, int $version, array $payload): void
    {
        $this->pendingEvents[] = ['type' => $type, 'version' => $version, 'payload' => $payload];
    }

    /** @return list<array{type: string, version: int, payload: array}> */
    public function popPendingEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        return $events;
    }
}
