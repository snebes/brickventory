<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros Admin/DashboardController; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace App\Inventory;

use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DashboardService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DocumentTotals $totals,
        private ReplenishmentService $replenishment,
        #[Autowire(env: 'default:dashboard_default_currency:INVENTORY_CURRENCY')]
        private string $currency,
        #[Autowire('%dashboard_default_currency%')]
        private string $defaultCurrency,
    ) {
    }

    public function overview(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $currency = preg_match('/^[A-Z]{3}$/D', $this->currency) ? $this->currency : $this->defaultCurrency;
        $db = $this->em->getConnection();
        // Aggregate physical balances once, then roll variants into their root product.
        $stock = $db->fetchAllKeyValue('SELECT product_id, SUM(quantity) FROM inv_balance GROUP BY product_id');
        $outbound = $db->fetchAllKeyValue('SELECT product_id, MAX(created_at) FROM inv_movement WHERE quantity < 0 GROUP BY product_id');
        $catalog = $db->fetchAllAssociative('SELECT p.*, c.name AS category_name FROM inv_product p LEFT JOIN inv_category c ON c.id = p.category_id ORDER BY p.id DESC');
        $roots = [];
        $physical = [];
        foreach ($catalog as $row) {
            $row['id'] = (int) $row['id'];
            $row['is_active'] = (bool) $row['is_active'];
            $row['min_stock'] = (int) $row['min_stock'];
            $physical[$row['id']] = $row;
            if (null === $row['parent_id']) {
                $roots[$row['id']] = [
                    'id' => $row['id'], 'name' => $row['name'], 'sku' => $row['sku'],
                    'is_active' => $row['is_active'], 'min_stock' => $row['min_stock'],
                    'stock' => 0, 'category_id' => null !== $row['category_id'] ? (int) $row['category_id'] : null,
                    'category' => $row['category_name'], 'created_at' => $row['created_at'],
                    'last_outbound' => null, 'total_value' => '0.00', 'cost_value' => '0.00',
                    'price' => $row['price'], 'cost' => $row['cost'], 'has_variants' => false,
                ];
            }
        }
        foreach ($physical as $row) {
            $rootId = (int) ($row['parent_id'] ?? $row['id']);
            if (!isset($roots[$rootId])) {
                continue;
            }
            $root = &$roots[$rootId];
            $quantity = (int) ($stock[$row['id']] ?? 0);
            $root['stock'] += $quantity;
            $root['has_variants'] = $root['has_variants'] || null !== $row['parent_id'];
            $last = $outbound[$row['id']] ?? null;
            if ($last && (!$root['last_outbound'] || $last > $root['last_outbound'])) {
                $root['last_outbound'] = $last;
            }
            if ($row['is_active'] && $root['is_active']) {
                $root['total_value'] = (string) BigDecimal::of($root['total_value'])->plus(BigDecimal::of($row['price'])->multipliedBy($quantity));
                $root['cost_value'] = (string) BigDecimal::of($root['cost_value'])->plus(BigDecimal::of($row['cost'])->multipliedBy($quantity));
            }
            unset($root);
        }
        $values = ['totalValue' => [$currency => '0.00'], 'revenueThisMonth' => [$currency => '0.00'], 'outstandingReceivables' => [$currency => '0.00'], 'deadStockValue' => [$currency => '0.00']];
        $stats = ['totalProducts' => count($roots), 'lowStockProducts' => 0, 'categories' => (int) $db->fetchOne('SELECT COUNT(*) FROM inv_category'), 'locations' => (int) $db->fetchOne('SELECT COUNT(*) FROM inv_location'), 'totalOrders' => 0, 'pendingOrders' => 0, 'deadStockCount' => 0, 'pendingPurchaseOrders' => 0, 'inTransitTransfers' => 0];
        $categories = [];
        $low = [];
        $top = [];
        $deadCutoff = $now->modify('-90 days')->format('Y-m-d H:i:s');
        foreach ($roots as $product) {
            if (!$product['is_active']) {
                continue;
            }
            if ($product['stock'] <= $product['min_stock']) {
                $low[] = $product;
                ++$stats['lowStockProducts'];
            }
            if ($product['stock'] > 0) {
                $top[] = $product;
            }
            $this->add($values['totalValue'], $currency, $product['total_value']);
            $key = $product['category_id'] ?? 'uncategorized';
            $categories[$key] ??= ['id' => $product['category_id'], 'name' => $product['category'] ?? 'Uncategorized', 'count' => 0, 'value' => '0.00'];
            ++$categories[$key]['count'];
            $categories[$key]['value'] = (string) BigDecimal::of($categories[$key]['value'])->plus($product['total_value']);
            if ($product['stock'] > 0 && $product['created_at'] <= $deadCutoff && (!$product['last_outbound'] || $product['last_outbound'] < $deadCutoff)) {
                ++$stats['deadStockCount'];
                $this->add($values['deadStockValue'], $currency, $product['cost_value']);
            }
        }
        $orders = [];
        $purchases = [];
        $suppliers = [];
        $monthStart = $now->format('Y-m-01');
        $monthEnd = $now->format('Y-m-t');
        // JSON line totals use the same decimal calculator as document writes; no float SQL arithmetic.
        $documents = $db->iterateAssociative('SELECT d.*, c.name AS customer_name, s.name AS supplier_name, s.is_active AS supplier_active FROM inv_document d LEFT JOIN inv_customer c ON c.id = d.customer_id LEFT JOIN inv_supplier s ON s.id = d.supplier_id ORDER BY d.created_at DESC, d.id DESC');
        foreach ($documents as $document) {
            $details = json_decode($document['details'], true, flags: JSON_THROW_ON_ERROR);
            $items = json_decode($document['items'], true, flags: JSON_THROW_ON_ERROR);
            $kind = $document['kind'];
            if ('stock-transfers' === $kind) {
                if ('shipped' === $document['status']) {
                    ++$stats['inTransitTransfers'];
                } continue;
            }
            if (!in_array($kind, ['orders', 'purchase-orders'], true)) {
                continue;
            }
            $documentCurrency = strtoupper($details['currency'] ?? $currency);
            if (!preg_match('/^[A-Z]{3}$/D', $documentCurrency)) {
                $documentCurrency = $currency;
            }
            $total = $this->totals->calculate($items, $details)['total'];
            $summary = ['id' => (int) $document['id'], 'number' => $document['number'], 'status' => $document['status'], 'total' => $total, 'currency' => $documentCurrency, 'created_at' => $document['created_at'], 'order_date' => $details['order_date'] ?? substr($document['created_at'], 0, 10)];
            if ('purchase-orders' === $kind) {
                if (in_array($document['status'], ['draft', 'sent', 'partial'], true)) {
                    ++$stats['pendingPurchaseOrders'];
                }
                if (count($purchases) < 5) {
                    $purchases[] = $summary + ['supplier' => $document['supplier_name']];
                }
                if ('cancelled' !== $document['status'] && $document['supplier_active']) {
                    foreach ($items as $line) {
                        $id = (int) $line['product_id'];
                        $suppliers[$id] ??= ['supplier_id' => (int) $document['supplier_id'], 'supplier' => $document['supplier_name'], 'unit_cost' => $line['unit_price'], 'currency' => $documentCurrency];
                    }
                }
                continue;
            }
            ++$stats['totalOrders'];
            if ('pending' === $document['status']) {
                ++$stats['pendingOrders'];
            }
            if (count($orders) < 5) {
                $orders[] = $summary + ['customer' => $details['customer_name'] ?? $document['customer_name'] ?? 'Customer'];
            }
            if ('cancelled' === $document['status']) {
                continue;
            }
            if ($summary['order_date'] >= $monthStart && $summary['order_date'] <= $monthEnd) {
                $this->add($values['revenueThisMonth'], $documentCurrency, $total);
            }
            $paid = BigDecimal::of('0.00');
            foreach ($details['payments'] ?? [] as $payment) {
                $paid = 'pay' === $payment['type'] ? $paid->plus($payment['amount']) : $paid->minus($payment['amount']);
            }
            $due = BigDecimal::of($total)->minus($paid);
            if ($due->isPositive()) {
                $this->add($values['outstandingReceivables'], $documentCurrency, (string) $due);
            }
        }
        foreach ($values as $key => $amounts) {
            $stats[$key] = $amounts[$currency] ?? '0.00';
            $rows = [['currency' => $currency, 'amount' => $stats[$key]]];
            ksort($amounts);
            foreach ($amounts as $code => $amount) {
                if ($code !== $currency) {
                    $rows[] = ['currency' => $code, 'amount' => $amount];
                }
            }
            $stats['byCurrency'][$key] = $rows;
        }
        usort($low, static fn ($a, $b) => $a['stock'] <=> $b['stock'] ?: $a['id'] <=> $b['id']);
        usort($top, static fn ($a, $b) => BigDecimal::of($b['total_value'])->compareTo($a['total_value']) ?: $a['id'] <=> $b['id']);
        $recent = array_values($roots);
        usort($recent, static fn ($a, $b) => strcmp($b['created_at'], $a['created_at']) ?: $b['id'] <=> $a['id']);
        $positions = array_column($this->replenishment->suggestions(false, false), null, 'id');
        $suggestions = array_slice(array_values(array_filter($positions, static fn ($row) => $row['needs_reorder'])), 0, 5);
        $low = array_map(static fn ($product) => $product + ($positions[$product['id']] ?? []), $low);

        $days = [];
        for ($i = 6; $i >= 0; --$i) {
            $days[$now->modify("-$i days")->format('Y-m-d')] = ['inbound' => 0, 'outbound' => 0, 'total' => 0];
        }
        $daily = $db->fetchAllAssociative('SELECT SUBSTR(created_at, 1, 10) AS day, SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) AS inbound, SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END) AS outbound FROM inv_movement WHERE created_at >= ? AND created_at <= ? GROUP BY SUBSTR(created_at, 1, 10)', [$now->modify('-6 days')->format('Y-m-d 00:00:00'), $now->format('Y-m-d H:i:s')]);
        foreach ($daily as $row) {
            if (isset($days[$row['day']])) {
                $days[$row['day']] = ['inbound' => (int) $row['inbound'], 'outbound' => (int) $row['outbound'], 'total' => (int) $row['inbound'] - (int) $row['outbound']];
            }
        }
        $activity = $db->fetchAllAssociative('SELECT m.id, m.type, m.quantity, m.reference, m.created_at, p.name AS product, p.sku, l.name AS location FROM inv_movement m JOIN inv_product p ON p.id = m.product_id JOIN inv_location l ON l.id = m.location_id ORDER BY m.created_at DESC, m.id DESC LIMIT 10');
        foreach ($activity as &$row) {
            $row['id'] = (int) $row['id'];
            $row['quantity'] = (int) $row['quantity'];
        } unset($row);

        return ['generatedAt' => $now->format(DATE_ATOM), 'currency' => $currency, 'stats' => $stats, 'recentProducts' => array_slice($recent, 0, 5), 'lowStockProducts' => array_slice($low, 0, 5), 'reorderSuggestions' => $suggestions, 'recentOrders' => $orders, 'recentPurchaseOrders' => $purchases, 'stockByCategory' => array_values($categories), 'topProducts' => array_slice($top, 0, 5), 'stockMovements' => array_map(static fn ($date, $amounts) => ['date' => $date] + $amounts, array_keys($days), array_values($days)), 'recentActivity' => $activity];
    }

    private function add(array &$amounts, string $currency, string $value): void
    {
        $amounts[$currency] = (string) BigDecimal::of($amounts[$currency] ?? '0.00')->plus($value)->toScale(2);
    }
}
