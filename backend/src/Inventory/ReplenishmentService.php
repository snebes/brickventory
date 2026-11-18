<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros ReorderService for the Symfony inventory model.

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\ReorderBatch;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ReplenishmentService
{
    public function __construct(private EntityManagerInterface $em, private InventoryService $inventory, private ManagerRegistry $registry)
    {
    }

    private function transaction(callable $work): array
    {
        $nested = $this->em->getConnection()->isTransactionActive();
        for ($attempt = 1;; ++$attempt) {
            try {
                return $this->em->wrapInTransaction($work);
            } catch (RetryableException $error) {
                if ($nested || $attempt >= 3) {
                    throw $error;
                }
                $this->em = $this->registry->resetManager();
                usleep(random_int(1000, 10000));
            }
        }
    }

    public function suggestions(bool $lock = false, bool $onlyDue = true): array
    {
        $db = $this->em->getConnection();
        $suffix = $lock && $db->getDatabasePlatform() instanceof AbstractMySQLPlatform ? ' FOR UPDATE' : '';
        $products = $db->fetchAllAssociative('SELECT * FROM inv_product ORDER BY id'.$suffix);
        $balances = $lock ? $db->fetchAllAssociative('SELECT product_id, quantity FROM inv_balance ORDER BY id'.$suffix) : $db->fetchAllAssociative('SELECT product_id, SUM(quantity) AS quantity FROM inv_balance GROUP BY product_id');
        $stock = [];
        foreach ($balances as $balance) {
            $stock[(int) $balance['product_id']] = ($stock[(int) $balance['product_id']] ?? 0) + (int) $balance['quantity'];
        }
        $links = $db->fetchAllAssociative('SELECT link.*, s.name AS supplier_name, s.is_active AS supplier_active FROM inv_product_supplier link JOIN inv_supplier s ON s.id = link.supplier_id ORDER BY link.product_id, link.id'.$suffix);
        $primary = [];
        foreach ($links as $link) {
            if ($link['is_primary']) {
                $primary[(int) $link['product_id']] = $link;
            }
        }
        $incoming = [];
        $reserved = [];
        $documents = $db->fetchAllAssociative("SELECT kind, status, items FROM inv_document WHERE (kind = 'purchase-orders' AND status IN ('draft','sent','partial')) OR (kind = 'stock-transfers' AND status = 'prepared') ORDER BY id".$suffix);
        foreach ($documents as $document) {
            foreach (json_decode($document['items'], true, flags: JSON_THROW_ON_ERROR) as $line) {
                $id = (int) ($line['product_variant_id'] ?? $line['product_id']);
                if ('purchase-orders' === $document['kind']) {
                    $incoming[$id] = ($incoming[$id] ?? 0) + max(0, $line['quantity'] - $line['received_quantity']);
                } else {
                    $reserved[$id] = ($reserved[$id] ?? 0) + $line['quantity'];
                }
            }
        }
        foreach ($db->fetchAllAssociative("SELECT t.product_id, t.quantity FROM inv_trace_balance t JOIN inv_product_serial s ON s.product_id = t.product_id AND s.serial_number = t.serial WHERE t.quantity > 0 AND s.status IN ('reserved', 'damaged')".$suffix) as $hold) {
            $reserved[(int) $hold['product_id']] = ($reserved[(int) $hold['product_id']] ?? 0) + (int) $hold['quantity'];
        }
        $catalog = [];
        $children = [];
        foreach ($products as $product) {
            $catalog[(int) $product['id']] = $product;
            if (null !== $product['parent_id'] && $product['is_active']) {
                $children[(int) $product['parent_id']][] = (int) $product['id'];
            }
        }
        $result = [];
        foreach ($catalog as $id => $product) {
            if (!$product['is_active'] || (null !== $product['parent_id'] && !($catalog[(int) $product['parent_id']]['is_active'] ?? false))) {
                continue;
            }
            $details = json_decode($product['details'], true, flags: JSON_THROW_ON_ERROR);
            if (null !== $product['parent_id'] && 0 === (int) $product['min_stock'] && !isset($details['reorder_point'])) {
                continue;
            }
            $members = null === $product['parent_id'] ? [$id, ...($children[$id] ?? [])] : [$id];
            $onHand = $onOrder = $onReserve = 0;
            foreach ($members as $member) {
                $onHand += $stock[$member] ?? 0;
                $onOrder += $incoming[$member] ?? 0;
                $onReserve += $reserved[$member] ?? 0;
            }
            $available = $onHand - $onReserve;
            $projected = $available + $onOrder;
            $point = (int) ($details['reorder_point'] ?? $product['min_stock']);
            $target = max($point, (int) ($details['max_stock'] ?? $point));
            $due = $projected <= $point && !($onOrder > 0 && $projected >= $target);
            if ($onlyDue && !$due) {
                continue;
            }
            $link = $primary[$id] ?? null;
            $quantity = max(1, (int) ($details['reorder_quantity'] ?? 0) ?: $target - $projected, (int) ($link['minimum_order_quantity'] ?? 1));
            $requiresVariant = null === $product['parent_id'] && !empty($children[$id]);
            $reason = !$due ? 'Projected stock already covers the reorder point.' : ($requiresVariant ? 'Select a variant to purchase.' : (!$link ? 'Choose a primary supplier.' : (!$link['supplier_active'] ? 'Primary supplier is inactive.' : ($quantity > 1000000000 ? 'Suggested quantity exceeds the supported limit.' : null))));
            $result[] = ['id' => $id, 'product_id' => null !== $product['parent_id'] ? (int) $product['parent_id'] : $id, 'product_variant_id' => null !== $product['parent_id'] ? $id : null, 'parent_id' => null !== $product['parent_id'] ? (int) $product['parent_id'] : null, 'name' => $product['name'], 'sku' => $product['sku'], 'stock' => $onHand, 'min_stock' => (int) $product['min_stock'], 'reorder_point' => $point, 'max_stock' => $target, 'reserved_quantity' => $onReserve, 'available_quantity' => $available, 'on_order_quantity' => $onOrder, 'projected_quantity' => $projected, 'needs_reorder' => $due, 'suggested_quantity' => $due ? $quantity : 0, 'supplier_id' => $link ? (int) $link['supplier_id'] : null, 'supplier' => $link['supplier_name'] ?? null, 'supplier_sku' => $link['supplier_sku'] ?? '', 'unit_cost' => $link['cost_price'] ?? $product['cost'], 'currency' => $link['currency'] ?? null, 'lead_time_days' => (int) ($link['lead_time_days'] ?? 0), 'has_variants' => $requiresVariant, 'can_create_draft' => null === $reason, 'blocked_reason' => $reason];
        }
        foreach ($result as &$row) {
            $row['fingerprint'] = hash('sha256', json_encode([$row['id'], $row['supplier_id'], $row['suggested_quantity'], $row['unit_cost'], $row['currency'], $row['supplier_sku'], $row['can_create_draft']], JSON_THROW_ON_ERROR));
        }
        unset($row);
        usort($result, static fn ($a, $b) => $a['projected_quantity'] <=> $b['projected_quantity'] ?: $a['id'] <=> $b['id']);

        return $result;
    }

    public function createDrafts(string $requestId, array $productIds, array $expected = []): array
    {
        sort($productIds);
        ksort($expected);
        if ($expected && (count($expected) !== count($productIds) || array_diff(array_keys($expected), $productIds))) {
            throw new UnprocessableEntityHttpException('A review fingerprint is required for each selected product.');
        }
        $hash = hash('sha256', json_encode([$productIds, $expected], JSON_THROW_ON_ERROR));

        return $this->transaction(function () use ($requestId, $productIds, $hash, $expected) {
            // Reorder batches use the same product rows as stock writers; acquire them in one order.
            // Current locking reads make concurrent batches observe newly-created draft PO quantities.
            $suffix = $this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform ? ' FOR UPDATE' : '';
            $this->em->getConnection()->fetchFirstColumn('SELECT id FROM inv_product ORDER BY id'.$suffix);
            $existing = $this->em->find(ReorderBatch::class, $requestId, LockMode::PESSIMISTIC_WRITE);
            if ($existing) {
                if (!hash_equals($existing->requestHash, $hash)) {
                    throw new ConflictHttpException('Request ID was already used for different products.');
                }

                return $existing->result;
            }
            $suggestions = array_column($this->suggestions(true), null, 'id');
            $groups = [];
            foreach ($productIds as $id) {
                $row = $suggestions[$id] ?? throw new UnprocessableEntityHttpException('Product #'.$id.' no longer needs replenishment. Refresh suggestions.');
                if ($expected && (!isset($expected[$id]) || !hash_equals($row['fingerprint'], $expected[$id]))) {
                    throw new ConflictHttpException('Purchasing terms or quantities changed. Refresh and review the selection again.');
                }
                if (!$row['can_create_draft']) {
                    throw new UnprocessableEntityHttpException($row['name'].': '.$row['blocked_reason']);
                }
                $key = $row['supplier_id'].':'.$row['currency'];
                $groups[$key] ??= ['supplier_id' => $row['supplier_id'], 'currency' => $row['currency'], 'order_date' => (new \DateTimeImmutable())->format('Y-m-d'), 'notes' => 'Created from replenishment review.', 'items' => []];
                $groups[$key]['items'][] = array_filter(['product_id' => $row['product_id'], 'product_variant_id' => $row['product_variant_id'], 'quantity' => $row['suggested_quantity'], 'unit_cost' => $row['unit_cost'], 'supplier_sku' => $row['supplier_sku']], static fn ($value) => null !== $value);
            }
            $orders = [];
            foreach ($groups as $group) {
                $orders[] = $this->inventory->view($this->inventory->create('purchase-orders', $group));
            }
            $batch = new ReorderBatch($requestId, $hash);
            $batch->result = ['purchase_orders' => $orders];
            $this->em->persist($batch);

            return $batch->result;
        });
    }
}
