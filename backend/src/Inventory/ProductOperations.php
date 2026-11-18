<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ProductOperations
{
    use TransactionalInventory;

    public function __construct(private EntityManagerInterface $em, private ManagerRegistry $registry, private InventoryService $inventory, private DocumentTotals $money)
    {
    }

    public function duplicate(int $id, array $data): array
    {
        $this->fields($data, ['sku', 'name']);

        return $this->transaction(function () use ($id, $data) {
            $source = $this->inventory->get('products', $id);
            $this->em->refresh($source, LockMode::PESSIMISTIC_WRITE);
            $body = array_intersect_key($source->details, array_flip(['unit', 'metadata', 'tracking', 'options', 'reorder_point', 'reorder_quantity', 'max_stock']));
            $body += ['sku' => $data['sku'] ?? '', 'name' => $data['name'] ?? $source->name.' (copy)', 'description' => $source->description, 'price' => $source->price, 'cost' => $source->cost, 'min_stock' => $source->minStock, 'is_active' => $source->isActive];
            if ($source->category) {
                $body['category_id'] = $source->category->id;
            }

            // Identity, variants, stock, supplier links, barcodes and attachments are independent.
            return $this->inventory->view($this->inventory->create('products', $body));
        });
    }

    public function bulk(string $action, array $data): array
    {
        $this->fields($data, match ($action) {
            'category' => ['ids', 'category_id'], 'price' => ['ids', 'type', 'value'], 'delete', 'export' => ['ids'], default => throw new UnprocessableEntityHttpException('Unknown bulk action.'),
        });
        $ids = $data['ids'] ?? [];
        if (!$ids || !array_is_list($ids) || count($ids) > 500 || count(array_unique($ids)) !== count($ids)) {
            throw new UnprocessableEntityHttpException('Select 1–500 unique products.');
        }
        sort($ids);

        return $this->transaction(function () use ($ids, $action, $data) {
            $products = [];
            foreach ($ids as $id) {
                $p = $this->inventory->get('products', $id);
                $this->em->refresh($p, LockMode::PESSIMISTIC_WRITE);
                $products[] = $p;
            }
            if ('export' === $action) {
                return array_map($this->inventory->view(...), $products);
            }
            foreach ($products as $p) {
                if ('delete' === $action) {
                    $this->inventory->delete('products', $p->id);
                } elseif ('category' === $action) {
                    $this->inventory->update('products', $p->id, ['category_id' => $data['category_id'] ?? throw new UnprocessableEntityHttpException('Choose a category.')]);
                } else {
                    $value = $data['value'] ?? '';
                    if (!preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', $value) || !in_array($data['type'] ?? '', ['fixed', 'percentage'], true)) {
                        throw new UnprocessableEntityHttpException('Choose fixed or percentage and a signed decimal value.');
                    }
                    $price = BigDecimal::of($p->price);
                    $delta = 'fixed' === $data['type'] ? BigDecimal::of($value) : $price->multipliedBy($value)->dividedBy(100, 2, RoundingMode::HalfUp);
                    $next = $price->plus($delta)->toScale(2, RoundingMode::HalfUp);
                    $this->inventory->update('products', $p->id, ['price' => $this->money->money($next->isNegative() ? '0' : (string) $next)]);
                }
            }

            return ['affected' => count($products)];
        });
    }

    private function fields(array $data, array $allowed): void
    {
        if (array_diff(array_keys($data), $allowed)) {
            throw new UnprocessableEntityHttpException('Unexpected operation fields.');
        }
    }
}
