<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

final class ProductImportRowException extends \RuntimeException
{
    public function __construct(public readonly int $row, string $message)
    {
        parent::__construct($message);
    }
}
