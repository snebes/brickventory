<?php

declare(strict_types=1);

namespace App\Entity\Inventory;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inv_attachment')]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Product $product;

    #[ORM\Column(length: 255)]
    public string $name;

    #[ORM\Column(length: 100)]
    public string $mimeType;

    #[ORM\Column(type: 'blob', length: 16777215)]
    public mixed $content;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
