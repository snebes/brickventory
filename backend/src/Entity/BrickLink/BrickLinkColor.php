<?php

declare(strict_types=1);

namespace App\Entity\BrickLink;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'bricklink_colors')]
class BrickLinkColor
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string')]
    #[Assert\NotBlank]
    public string $name = '';

    #[ORM\Column(type: 'string', length: 10)]
    public string $rgb = '';

    #[ORM\Column(type: 'string')]
    public string $type = '';

    #[ORM\Column(type: 'string', length: 5)]
    public string $yearFrom = '';

    #[ORM\Column(type: 'string', length: 5)]
    public string $yearTo = '';
}
