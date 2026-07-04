<?php

declare(strict_types=1);

namespace App\Message\BrickLink;

class AddBrickLinkColorCodesMessage
{
    public function __construct(
        public readonly string $xmlFile,
    ) {
    }
}
