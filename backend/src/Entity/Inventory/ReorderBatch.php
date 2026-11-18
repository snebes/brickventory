<?php

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_reorder_batch')]
class ReorderBatch
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    public string $id;

    #[ORM\Column(length: 64)]
    public string $requestHash;

    #[ORM\Column(type: 'json')]
    public array $result = [];

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $requestHash)
    {
        $this->id = $id;
        $this->requestHash = $requestHash;
        $this->createdAt = new \DateTimeImmutable();
    }
}
