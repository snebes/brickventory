<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

final class ProductCsv
{
    private const FIELDS = ['sku', 'name', 'description', 'price', 'cost', 'barcode', 'min_stock', 'is_active', 'category_id', 'unit', 'tracking', 'reorder_point', 'reorder_quantity', 'max_stock'];

    public function header(array $header): array
    {
        $header = array_map(static fn ($v) => trim((string) $v), $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
        if (!in_array('sku', $header, true) || count(array_unique($header)) !== count($header) || array_diff($header, self::FIELDS)) {
            throw new ProductImportRowException(1, 'CSV requires a unique sku header and supported product columns.');
        }

        return $header;
    }

    public function row(array $header, array $values, int $number): array
    {
        if (count($values) !== count($header)) {
            throw new ProductImportRowException($number, 'Column count does not match the header.');
        }
        $row = array_combine($header, $values);
        foreach ($row as $value) {
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || strlen($value) > 10000) {
                throw new ProductImportRowException($number, 'Fields must be UTF-8 text up to 10000 bytes.');
            }
        }
        if ('' === trim($row['sku'])) {
            throw new ProductImportRowException($number, 'SKU is required.');
        }
        foreach (['min_stock', 'category_id', 'reorder_point', 'reorder_quantity', 'max_stock'] as $key) {
            if (!isset($row[$key])) {
                continue;
            }
            if ('' === $row[$key]) {
                unset($row[$key]);
                continue;
            }
            if (!preg_match('/^\d{1,10}$/D', $row[$key]) || (int) $row[$key] > 1000000000) {
                throw new ProductImportRowException($number, 'Invalid '.$key.'.');
            }
            $row[$key] = (int) $row[$key];
        }
        if (isset($row['is_active'])) {
            $row['is_active'] = filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null === $row['is_active']) {
                throw new ProductImportRowException($number, 'Invalid is_active value.');
            }
        }
        if (isset($row['barcode']) && '' === $row['barcode']) {
            unset($row['barcode']);
        }

        return $row;
    }
}
