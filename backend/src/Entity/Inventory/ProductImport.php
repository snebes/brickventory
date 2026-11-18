<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_product_import')]
class ProductImport
{
    #[ORM\Id, ORM\Column(length: 36)]
    public string $id;
    #[ORM\Column(length: 255)]
    public string $filename = '';
    #[ORM\Column(length: 30)]
    public string $status = 'queued';
    #[ORM\Column]
    public int $fileSize = 0;
    #[ORM\Column(name: '`cursor`', type: 'bigint')]
    public int $cursor = 0;
    #[ORM\Column]
    public int $processed = 0;
    #[ORM\Column]
    public int $recordsRead = 0;
    #[ORM\Column(type: 'json')]
    public array $header = [];
    #[ORM\Column(type: 'json')]
    public array $error = [];
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
    #[ORM\Column]
    public \DateTimeImmutable $updatedAt;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }
}
