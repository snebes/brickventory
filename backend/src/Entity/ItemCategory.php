<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Validate;

#[ORM\Entity]
class ItemCategory extends AbstractMasterDataEntity
{
    #[ORM\Column(type: 'text')]
    public string $name = '';

    public function __construct()
    {
        parent::__construct();
    }
}
