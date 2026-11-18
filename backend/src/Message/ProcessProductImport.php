<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessProductImport
{
    public function __construct(public string $id, public int $cursor = 0)
    {
    }
}
