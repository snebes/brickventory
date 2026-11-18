<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_cycle_count_schedule')]
class CycleCountSchedule
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(length: 255)]
    public string $name = '';
    #[ORM\Column(type: 'json')]
    public array $details = [];
    #[ORM\Column(type: 'boolean')]
    public bool $isActive = true;
    #[ORM\Column]
    public \DateTimeImmutable $nextRunAt;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $lastRunAt = null;

    public function __construct()
    {
        $this->nextRunAt = new \DateTimeImmutable();
    }
}
