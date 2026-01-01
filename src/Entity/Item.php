<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    public private(set) string $uuid = '';

    #[ORM\Column(type: 'string', length: 55, unique: true)]
    #[Validate\NotBlank]
    public string $itemId = '';

    #[ORM\Column(type: 'text')]
    public string $itemName = '';

    #[ORM\Column(type: 'integer')]
    public int $quantityAvailable = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityOnHand = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityOnOrder = 0;

    #[ORM\Column(type: 'integer')]
    public int $quantityBackOrdered = 0;

    public function __construct()
    {
        $this->uuid = Ulid::generate();
    }
}
