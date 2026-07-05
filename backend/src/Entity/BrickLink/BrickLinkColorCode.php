<?php

declare(strict_types=1);

namespace App\Entity\BrickLink;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'bricklink_color_codes')]
class BrickLinkColorCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\ManyToOne(targetEntity: BrickLinkPart::class)]
    public BrickLinkPart $part;

    #[ORM\ManyToOne(targetEntity: BrickLinkColor::class)]
    public BrickLinkColor $color;

    #[ORM\Column(type: 'string', length: 100, index: true)]
    #[Assert\NotBlank]
    public string $itemId = '';

    #[ORM\Column(type: 'json')]
    public array $elementIds = [];
}
