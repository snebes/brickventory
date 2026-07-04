<?php

declare(strict_types=1);

namespace App\Entity\BrickLink;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/*
    <ITEM>
      <ITEMTYPE>P</ITEMTYPE>
      <ITEMID>sticker</ITEMID>
      <ITEMNAME>Sticker &amp;#40;Unsorted, Generic Entry&amp;#41;</ITEMNAME>
      <ALTITEMIDS>8186,118746</ALTITEMIDS>
      <CATEGORY>160</CATEGORY>
      <ITEMWEIGHT></ITEMWEIGHT>
      <ITEMDIMX></ITEMDIMX>
      <ITEMDIMY></ITEMDIMY>
      <ITEMDIMZ></ITEMDIMZ>
   </ITEM>
 */

#[ORM\Entity]
#[ORM\Table(name: 'bricklink_parts')]
class BrickLinkPart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string', length: 10)]
    public string $type = '';

    #[ORM\Column(type: 'string', length: 100, index: true)]
    #[Assert\NotBlank]
    public string $part = '';

    #[ORM\Column(type: 'text')]
    public string $name = '';

    #[ORM\Column(type: 'json')]
    public array $altIds = [];

    #[ORM\ManyToOne(targetEntity: BrickLinkCategory::class)]
    public BrickLinkCategory $category;

    #[ORM\Column(type: 'float')]
    public float $weight = 0;

    #[ORM\Column(type: 'float')]
    public float $dimensionX = 0;

    #[ORM\Column(type: 'float')]
    public float $dimensionY = 0;

    #[ORM\Column(type: 'float')]
    public float $dimensionZ = 0;
}
