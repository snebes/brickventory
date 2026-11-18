<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class ProductImportStorage
{
    public function __construct(#[Autowire('%env(resolve:INVENTORY_IMPORT_DIRECTORY)%')] private string $directory)
    {
    }

    public function path(string $id): string
    {
        if (!Uuid::isValid($id)) {
            throw new \InvalidArgumentException('Invalid import identity.');
        }

        return rtrim($this->directory, '/').'/'.$id.'.csv';
    }

    public function store(string $id, UploadedFile $file): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Import storage unavailable.');
        }
        $file->move($this->directory, basename($this->path($id)));
        chmod($this->path($id), 0600);
    }

    public function delete(string $id): void
    {
        $path = $this->path($id);
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Unable to remove import source.');
        }
    }
}
