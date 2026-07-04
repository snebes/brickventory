<?php

declare(strict_types=1);

namespace App\Message\BrickLink;

class AddBrickLinkPartsMessage
{
    public function __construct(
        public readonly string $xmlFile,
    ) {
    }
}
