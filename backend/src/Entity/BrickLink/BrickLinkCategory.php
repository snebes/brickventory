<?php

declare(strict_types=1);

namespace App\Entity\BrickLink;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'bricklink_categories')]
class BrickLinkCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string')]
    #[Assert\NotBlank]
    public string $name = '';
}
