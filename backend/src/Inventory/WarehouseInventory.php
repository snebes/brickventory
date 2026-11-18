<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\Balance;
use App\Entity\Inventory\Product;
use App\Entity\Inventory\Warehouse;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class WarehouseInventory
{
    use TransactionalInventory;

    public function __construct(private EntityManagerInterface $em, private ManagerRegistry $registry, private InventoryService $inventory, private InventoryStockLedger $ledger)
    {
    }

    public function levels(int $productId): array
    {
        $p = $this->inventory->getProduct($productId);
        $rows = [];
        foreach ($this->em->getRepository(Warehouse::class)->findBy([], ['name' => 'ASC']) as $w) {
            $members = [$p, ...($p->parent ? [] : $this->em->getRepository(Product::class)->findBy(['parent' => $p]))];
            $stock = $reserved = 0;
            foreach ($this->em->getRepository(Balance::class)->findBy(['product' => $members]) as $balance) {
                if ($balance->location->warehouse?->id !== $w->id) {
                    continue;
                }
                $stock += $balance->quantity;
                $reserved += $this->ledger->reserved($balance->product, $balance->location);
            }
            $levels = $p->details['warehouse_levels'][(string) $w->id] ?? [];
            $minimum = $levels['reorder_point'] ?? $levels['min_stock'] ?? $p->details['reorder_point'] ?? $p->minStock;
            $maximum = $levels['max_stock'] ?? $p->details['max_stock'] ?? null;
            $rows[] = ['warehouse_id' => $w->id, 'warehouse' => $w->name, 'is_default' => $w->details['is_default'] ?? false, 'stock' => $stock, 'reserved_quantity' => $reserved, 'available_quantity' => $stock - $reserved, 'levels' => $levels, 'effective_min_stock' => $minimum, 'low_stock' => $stock - $reserved <= $minimum, 'over_capacity' => null !== $maximum && $stock > $maximum];
        }

        return $rows;
    }

    public function saveLevels(int $productId, array $data): array
    {
        if (array_diff(array_keys($data), ['levels']) || !isset($data['levels']) || !array_is_list($data['levels']) || count($data['levels']) > 100) {
            throw new UnprocessableEntityHttpException('Provide up to 100 warehouse levels.');
        }

        return $this->transaction(function () use ($productId, $data) {
            $p = $this->inventory->getProduct($productId);
            $this->em->refresh($p, LockMode::PESSIMISTIC_WRITE);
            $seen = [];
            foreach ($data['levels'] as $level) {
                if (!is_array($level) || array_diff(array_keys($level), ['warehouse_id', 'min_stock', 'max_stock', 'reorder_point', 'reorder_quantity']) || !is_int($level['warehouse_id'] ?? null)) {
                    throw new UnprocessableEntityHttpException('Invalid warehouse level.');
                }
                $id = $level['warehouse_id'];
                $this->inventory->get('warehouses', $id);
                if (isset($seen[$id])) {
                    throw new UnprocessableEntityHttpException('Duplicate warehouse.');
                }
                $seen[$id] = true;
                unset($level['warehouse_id']);
                $level = array_filter($level, static fn ($v) => null !== $v);
                foreach ($level as $value) {
                    if (!is_int($value) || $value < 0 || $value > 1000000000) {
                        throw new UnprocessableEntityHttpException('Warehouse levels must be nonnegative integers.');
                    }
                }
                if (isset($level['max_stock']) && $level['max_stock'] < max($level['min_stock'] ?? 0, $level['reorder_point'] ?? 0)) {
                    throw new UnprocessableEntityHttpException('Maximum stock must cover minimum stock and reorder point.');
                }
                if ($level) {
                    $p->details['warehouse_levels'][(string) $id] = $level;
                } else {
                    unset($p->details['warehouse_levels'][(string) $id]);
                }
            }
            $this->em->flush();

            return $this->levels($productId);
        });
    }

    public function setDefault(int $id): array
    {
        return $this->transaction(function () use ($id) {
            $this->inventory->get('warehouses', $id);
            $rows = $this->em->getRepository(Warehouse::class)->createQueryBuilder('w')->orderBy('w.id')->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getResult();
            foreach ($rows as $w) {
                $w->details['is_default'] = $w->id === $id;
            }

            return ['warehouse_id' => $id];
        });
    }
}
