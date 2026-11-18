<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\Balance;
use App\Entity\Inventory\Category;
use App\Entity\Inventory\Customer;
use App\Entity\Inventory\Document;
use App\Entity\Inventory\Location;
use App\Entity\Inventory\Movement;
use App\Entity\Inventory\Product;
use App\Entity\Inventory\Supplier;
use App\Entity\Inventory\TraceBalance;
use App\Entity\Inventory\Warehouse;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InventoryService
{
    private const RESOURCES = ['trace-balances' => TraceBalance::class, 'categories' => Category::class, 'warehouses' => Warehouse::class, 'products' => Product::class, 'variants' => Product::class, 'customers' => Customer::class, 'suppliers' => Supplier::class, 'locations' => Location::class, 'orders' => Document::class, 'purchase-orders' => Document::class, 'stock-transfers' => Document::class, 'stock-audits' => Document::class, 'order-returns' => Document::class, 'stock-adjustments' => Movement::class, 'stock-movements' => Movement::class, 'stock-balances' => Balance::class];

    public function __construct(private EntityManagerInterface $em, private DocumentTotals $totals, private ManagerRegistry $registry, private InventoryStockLedger $ledger)
    {
    }

    private function transaction(callable $work): mixed
    {
        if (!$this->em->isOpen()) {
            $this->em = $this->registry->getManager();
        }
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

    public function importCsv(\Symfony\Component\HttpFoundation\File\UploadedFile $file): array
    {
        $stream = fopen($file->getPathname(), 'rb');
        try {
            $header = fgetcsv($stream, escape: '');
            if (!$header || count(array_unique($header)) !== count($header)) {
                $this->invalid('CSV requires a header with unique fields.');
            }
            $header[0] = preg_replace('/^\\xEF\\xBB\\xBF/', '', $header[0]);
            $rows = [];
            while (($values = fgetcsv($stream, escape: '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header) || count($rows) >= 250000) {
                    $this->invalid('Invalid CSV row or more than 500 rows.');
                }
                $row = array_combine($header, $values);
                if (isset($row['is_active'])) {
                    $row['is_active'] = filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if (null === $row['is_active']) {
                        $this->invalid('Invalid CSV is_active value.');
                    }
                }
                foreach (['min_stock', 'category_id'] as $field) {
                    if (!isset($row[$field])) {
                        continue;
                    }
                    if (!preg_match('/^\\d{1,9}$/D', $row[$field])) {
                        $this->invalid('Invalid CSV '.$field.'.');
                    }
                    $row[$field] = (int) $row[$field];
                }
                if (isset($row['barcode']) && '' === $row['barcode']) {
                    unset($row['barcode']);
                }
                $rows[] = $row;
            }

            return $this->importProducts($rows);
        } finally {
            fclose($stream);
        }
    }

    public function listing(string $resource, int $page, int $limit, ?string $status, ?int $productId, array $filters = []): array
    {
        $criteria = $this->criteria($resource);
        $class = self::RESOURCES[$resource];
        $scopedLowStock = Product::class === $class && isset($filters['warehouse_id']) && filter_var($filters['low_stock'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (null !== $status && Document::class === $class) {
            $criteria['status'] = $status;
        }
        if (null !== $productId && in_array($class, [Balance::class, Movement::class, TraceBalance::class], true)) {
            $criteria['product'] = $this->getProduct($productId);
        }
        $qb = $this->em->getRepository($class)->createQueryBuilder('r');
        foreach ($criteria as $field => $value) {
            if ('__not_null__' === $value) {
                $qb->andWhere('r.'.$field.' IS NOT NULL');
                continue;
            }
            if (null === $value) {
                $qb->andWhere('r.'.$field.' IS NULL');
                continue;
            }
            $qb->andWhere(is_array($value) ? 'r.'.$field.' IN (:'.$field.')' : 'r.'.$field.' = :'.$field)->setParameter($field, $value);
        }
        if (isset($filters['is_active']) && in_array($class, [Product::class, Customer::class, Supplier::class], true)) {
            $active = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null === $active) {
                $this->invalid('Invalid is_active filter.');
            }
            $qb->andWhere('r.isActive = :active')->setParameter('active', $active);
        }
        foreach (['category_id' => 'category', 'parent_id' => 'parent'] as $input => $field) {
            if (Product::class === $class && isset($filters[$input])) {
                $qb->andWhere('IDENTITY(r.'.$field.') = :'.$field.'Filter')->setParameter($field.'Filter', (int) $filters[$input]);
            }
        }
        if (Product::class === $class) {
            if (!$scopedLowStock && isset($filters['low_stock']) && filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN)) {
                $qb->andWhere('(SELECT COALESCE(SUM(lb.quantity), 0) FROM App\\Entity\\Inventory\\Balance lb JOIN lb.product lp WHERE lp = r OR IDENTITY(lp.parent) = r.id) <= r.minStock');
            }
            if (isset($filters['warehouse_id'])) {
                $this->get('warehouses', (int) $filters['warehouse_id']);
            }
            if (isset($filters['location_id'])) {
                $sub = 'EXISTS (SELECT fb.id FROM App\\Entity\\Inventory\\Balance fb JOIN fb.location fl JOIN fb.product fp WHERE (fp = r OR IDENTITY(fp.parent) = r.id)';
                if (isset($filters['location_id'])) {
                    $sub .= ' AND fl.id = :locationFilter';
                    $qb->setParameter('locationFilter', (int) $filters['location_id']);
                }
                if (isset($filters['warehouse_id'])) {
                    $sub .= ' AND IDENTITY(fl.warehouse) = :warehouseFilter';
                    $qb->setParameter('warehouseFilter', (int) $filters['warehouse_id']);
                }
                $qb->andWhere($sub.')');
            }
        }
        if (Location::class === $class && isset($filters['warehouse_id'])) {
            $qb->andWhere('IDENTITY(r.warehouse) = :warehouseFilter')->setParameter('warehouseFilter', (int) $filters['warehouse_id']);
        }
        if (in_array($class, [Balance::class, Movement::class, TraceBalance::class], true) && isset($filters['warehouse_id'])) {
            $qb->join('r.location', 'warehouseLocation')->andWhere('IDENTITY(warehouseLocation.warehouse) = :stockWarehouse')->setParameter('stockWarehouse', (int) $filters['warehouse_id']);
        }
        if (in_array($class, [Balance::class, Movement::class, TraceBalance::class], true) && isset($filters['location_id'])) {
            $qb->andWhere('IDENTITY(r.location) = :locationFilter')->setParameter('locationFilter', (int) $filters['location_id']);
        }
        foreach (['customer_id' => 'customer', 'supplier_id' => 'supplier'] as $input => $field) {
            if (Document::class === $class && isset($filters[$input])) {
                $qb->andWhere('IDENTITY(r.'.$field.') = :'.$field)->setParameter($field, (int) $filters[$input]);
            }
        }
        if (!empty($filters['search'])) {
            $fields = match ($class) {
                Product::class => ['name', 'sku', 'description', 'barcode'], Customer::class, Supplier::class => ['name', 'email', 'code'], Location::class => ['name', 'code'], Category::class, Warehouse::class => ['name'], Document::class => ['number'], default => [],
            };
            if ($fields) {
                $qb->andWhere($qb->expr()->orX(...array_map(fn ($field) => 'LOWER(r.'.$field.') LIKE :search', $fields)))->setParameter('search', '%'.strtolower($filters['search']).'%');
            }
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $field => $operator) {
            if (empty($filters[$field])) {
                continue;
            }
            $this->validateDetails(['order_date' => $filters[$field]]);
            $date = new \DateTimeImmutable($filters[$field].('date_to' === $field ? ' 23:59:59' : ' 00:00:00'));
            $qb->andWhere('r.createdAt '.$operator.' :'.$field)->setParameter($field, $date);
        }
        $sorts = ['id' => 'id', 'created_at' => 'createdAt'];
        if (in_array($class, [Product::class, Customer::class, Supplier::class, Location::class, Category::class, Warehouse::class], true)) {
            $sorts['name'] = 'name';
        }
        if (Product::class === $class) {
            $sorts += ['sku' => 'sku'];
        }
        $sort = $sorts[$filters['sort_by'] ?? 'id'] ?? throw new UnprocessableEntityHttpException('Unsupported sort field.');
        $direction = strtoupper($filters['sort_dir'] ?? 'DESC');
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $this->invalid('Invalid sort direction.');
        }
        $total = (int) (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $qb->orderBy('r.'.$sort, $direction);
        if ('id' !== $sort) {
            $qb->addOrderBy('r.id', $direction);
        }
        if (!$scopedLowStock) {
            $qb->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        }
        $records = $qb->getQuery()->getResult();

        $views = array_map($this->view(...), $records);
        if (Product::class === $class && isset($filters['warehouse_id'])) {
            foreach ($records as $index => $product) {
                $balances = $this->em->createQuery('SELECT wb FROM App\\Entity\\Inventory\\Balance wb JOIN wb.location wl JOIN wb.product wp WHERE (wp = :product OR IDENTITY(wp.parent) = :id) AND IDENTITY(wl.warehouse) = :warehouse')->setParameter('product', $product)->setParameter('id', $product->id)->setParameter('warehouse', (int) $filters['warehouse_id'])->getResult();
                $stock = $reserved = 0;
                foreach ($balances as $balance) {
                    $stock += $balance->quantity;
                    $reserved += $this->ledger->reserved($balance->product, $balance->location);
                }
                $views[$index]['stock'] = $stock;
                $views[$index]['reserved_quantity'] = $reserved;
                $views[$index]['available_quantity'] = $stock - $reserved;
                $views[$index]['warehouse_id'] = (int) $filters['warehouse_id'];
                $views[$index]['warehouse_levels'] = $product->details['warehouse_levels'][(string) $filters['warehouse_id']] ?? [];
            }
        }

        if ($scopedLowStock) {
            $views = array_values(array_filter($views, static fn ($v) => $v['available_quantity'] <= ($v['warehouse_levels']['reorder_point'] ?? $v['warehouse_levels']['min_stock'] ?? $v['details']['reorder_point'] ?? $v['min_stock'])));
            $total = count($views);
            $views = array_slice($views, ($page - 1) * $limit, $limit);
        }

        return ['data' => $views, 'meta' => ['current_page' => $page, 'per_page' => $limit, 'total' => $total]];
    }

    public function barcode(string $barcode): array
    {
        $product = $this->em->getRepository(Product::class)->findOneBy(['barcode' => $barcode, 'isActive' => true]) ?? throw new NotFoundHttpException('Barcode not found.');

        return $this->view($product);
    }

    public function importProducts(array $rows): array
    {
        if (!$rows || !array_is_list($rows) || count($rows) > 255000) {
            $this->invalid('Import requires between 1 and 500 rows.');
        }

        return $this->transaction(function () use ($rows) {
            $result = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    $this->invalid('Invalid import row.');
                }
                $existing = isset($row['sku']) ? $this->em->getRepository(Product::class)->findOneBy(['sku' => $row['sku']]) : null;
                if ($existing?->parent) {
                    $this->invalid('Variant SKU cannot be imported as a product.');
                }
                $record = $existing ? $this->update('products', $existing->id, $row) : $this->create('products', $row);
                $result[] = $this->view($record);
            }

            return $result;
        });
    }

    public function exportProducts(): iterable
    {
        $query = $this->em->getRepository(Product::class)->createQueryBuilder('p')->where('p.parent IS NULL')->orderBy('p.id', 'ASC')->getQuery();
        foreach ($query->toIterable() as $product) {
            yield [$product->sku, $product->name, $product->description, $product->price, $product->cost, $product->barcode ?? '', $product->minStock, $product->isActive ? '1' : '0'];
        }
    }

    public function get(string $resource, int $id): object
    {
        $criteria = $this->criteria($resource) + ['id' => $id];
        if ('variants' === $resource) {
            unset($criteria['parent']);
        }
        $record = $this->em->getRepository(self::RESOURCES[$resource])->findOneBy($criteria) ?? throw new NotFoundHttpException('Record not found.');
        if ('variants' === $resource && !$record->parent) {
            throw new NotFoundHttpException('Variant not found.');
        }

        return $record;
    }

    public function getProduct(int $id): Product
    {
        return $this->em->find(Product::class, $id) ?? throw new NotFoundHttpException('Product not found.');
    }

    private function criteria(string $resource): array
    {
        if (!isset(self::RESOURCES[$resource])) {
            throw new NotFoundHttpException('Unknown resource.');
        }
        if ('variants' === $resource) {
            return ['parent' => '__not_null__'];
        }
        if ('products' === $resource) {
            return ['parent' => null];
        }
        if ('stock-adjustments' === $resource) {
            return ['type' => ['manual', 'count', 'damage', 'return'], 'reference' => ''];
        }

        return Document::class === self::RESOURCES[$resource] ? ['kind' => $resource] : [];
    }

    public function create(string $resource, array $data): object
    {
        $this->criteria($resource);

        return $this->transaction(function () use ($resource, $data) {
            $class = self::RESOURCES[$resource];
            if (Document::class === $class || 'stock-adjustments' === $resource) {
                $allowed = match ($resource) {
                    'orders' => ['customer_id', 'customer_name', 'location_id', 'items', 'notes', 'status', 'tax', 'shipping', 'discount_type', 'discount_value', 'currency', 'order_date', 'customer_email', 'customer_address', 'source', 'external_id', 'metadata', 'approval_required'],
                    'purchase-orders' => ['supplier_id', 'items', 'notes', 'status', 'tax', 'shipping', 'currency', 'order_date', 'expected_date', 'approval_required'],
                    'order-returns' => ['order_id', 'items', 'reason', 'notes'],
                    'stock-audits' => ['location_id', 'items', 'notes'],
                    'stock-transfers' => ['from_location_id', 'to_location_id', 'items', 'notes', 'status', 'approval_required', 'shipping_method', 'estimated_arrival'],
                    default => ['product_id', 'location_id', 'quantity', 'type', 'reason', 'product_variant_id', 'batch', 'serials', 'expires_at'],
                };
                $this->fields($data, $allowed);
            }
            if (TraceBalance::class === $class || Balance::class === $class || 'stock-movements' === $resource) {
                $this->invalid('This ledger is read only.');
            }
            if (Movement::class === $class) {
                $product = $this->stockProduct($data);
                if (isset($data['product_variant_id']) && $product->parent?->id !== ($data['product_id'] ?? null)) {
                    $this->invalid('Invalid variant.');
                }
                $location = $this->get('locations', $this->positive($data, 'location_id'));
                $quantity = $this->integer($data, 'quantity');
                $type = $data['type'] ?? 'manual';
                if (!in_array($type, ['manual', 'count', 'damage', 'return'], true)) {
                    $this->invalid('Invalid adjustment type.');
                }
                if (0 === $quantity) {
                    $this->invalid('Quantity must be nonzero.');
                }

                return $this->ledger->move($product, $location, $quantity, $type, '', $data['reason'] ?? '', $data);
            }
            $record = new $class();
            if ($record instanceof Document) {
                $record->kind = $resource;
                $record->number = strtoupper(substr($resource, 0, 2)).'-'.bin2hex(random_bytes(10));
                $record->status = match ($resource) {
                    'orders' => 'pending', 'order-returns' => 'requested', default => 'draft',
                };
                $record->details = $data;
                unset($record->details['items']);
                if (!empty($data['approval_required'])) {
                    $record->details['approval_status'] = 'pending';
                }
                $record->items = 'order-returns' === $resource ? $this->returnRequestLines($data) : ('stock-audits' === $resource ? $this->countLines($data) : $this->lines($data['items'] ?? [], $resource));
                $this->validateDetails($record->details);
                $this->totals->calculate($record->items, $record->details);
                if ('orders' === $resource) {
                    if (isset($data['customer_id'])) {
                        $record->customer = $this->get('customers', $this->positive($data, 'customer_id'));
                    }
                    if (!$record->customer && empty($data['customer_name'])) {
                        $this->invalid('customer_id or customer_name is required.');
                    }
                    $location = $this->get('locations', $this->positive($data, 'location_id'));
                    $record->items = $this->traceDetails($record->items, $location);
                    foreach ($record->items as $line) {
                        $this->ledger->move($this->stockProduct($line), $location, -$line['quantity'], 'sale', $record->number, '', $line);
                    }
                } elseif ('purchase-orders' === $resource) {
                    $record->supplier = $this->get('suppliers', $this->positive($data, 'supplier_id'));
                } elseif ('order-returns' === $resource) {
                    // Return request validation runs under the source order lock.
                } elseif ('stock-audits' === $resource) {
                    $this->get('locations', $this->positive($data, 'location_id'));
                } else {
                    $from = $this->positive($data, 'from_location_id');
                    $to = $this->positive($data, 'to_location_id');
                    $record->items = $this->traceDetails($record->items, $this->get('locations', $from));
                    $this->get('locations', $to);
                    if ($from === $to) {
                        $this->invalid('Transfer locations must differ.');
                    }
                }
                if (isset($data['status']) && $data['status'] !== $record->status) {
                    $this->invalid('Use a workflow action to change status.');
                }
            } else {
                if ('variants' === $resource) {
                    $record->parent = $this->get('products', $this->positive($data, 'parent_id'));
                    unset($data['parent_id']);
                }
                $this->editMaster($record, $data);
            }
            $this->em->persist($record);

            return $record;
        });
    }

    public function update(string $resource, int $id, array $data): object
    {
        return $this->transaction(function () use ($resource, $id, $data) {
            $record = $this->get($resource, $id);
            $this->em->refresh($record, LockMode::PESSIMISTIC_WRITE);
            if ($record instanceof Document) {
                $common = ['notes', 'currency', 'order_date', 'tax', 'shipping'];
                if ('orders' === $record->kind) {
                    $this->fields($data, [...$common, 'items', 'customer_id', 'customer_name', 'customer_email', 'customer_address', 'discount_type', 'discount_value', 'status', 'metadata', 'source', 'external_id']);
                    if (array_diff(array_keys($data), ['notes', 'status']) && !in_array($record->status, ['pending', 'processing'], true)) {
                        $this->invalid('Only open orders can be edited.');
                    }
                    if (isset($data['items'])) {
                        $lines = $this->traceDetails($this->lines($data['items'], 'orders'), $this->get('locations', $record->details['location_id']));
                        $location = $this->get('locations', $record->details['location_id']);
                        foreach ($record->items as $line) {
                            $this->ledger->move($this->stockProduct($line), $location, $line['quantity'], 'order_edit_return', $record->number, '', $line);
                        }
                        foreach ($lines as $line) {
                            $this->ledger->move($this->stockProduct($line), $location, -$line['quantity'], 'sale', $record->number, '', $line);
                        }
                        $record->items = $lines;
                    }
                    if (isset($data['customer_id'])) {
                        $record->customer = $this->get('customers', $this->positive($data, 'customer_id'));
                    }
                } else {
                    if ('draft' !== $record->status) {
                        $this->invalid('Only draft documents can be edited.');
                    }
                    if (in_array($record->kind, ['stock-audits', 'order-returns'], true)) {
                        $this->invalid('Counts are immutable; cancel and create a replacement.');
                    }
                    $allowed = 'purchase-orders' === $record->kind ? [...$common, 'items', 'supplier_id', 'expected_date'] : ['items', 'notes'];
                    $this->fields($data, $allowed);
                    if (isset($data['supplier_id'])) {
                        $record->supplier = $this->get('suppliers', $this->positive($data, 'supplier_id'));
                    }
                    if (isset($data['items'])) {
                        $record->items = $this->lines($data['items'], $record->kind);
                    }
                }
                if (!empty($record->details['approval_required']) && array_diff(array_keys($data), ['status', 'notes'])) {
                    $record->details['approval_status'] = 'pending';
                }
                $details = $data;
                unset($details['items'], $details['status']);
                $record->details = array_replace($record->details, $details);
                $this->validateDetails($record->details);
                $this->totals->calculate($record->items, $record->details);
                if (isset($data['status'])) {
                    $this->transitionOrder($record, $data['status']);
                }
            } elseif ($record instanceof TraceBalance || $record instanceof Movement || $record instanceof Balance) {
                $this->invalid('Stock ledgers are immutable.');
            } else {
                $this->editMaster($record, $data);
            }

            return $record;
        });
    }

    public function delete(string $resource, int $id): void
    {
        $this->transaction(function () use ($resource, $id) {
            $record = $this->get($resource, $id);
            $this->em->refresh($record, LockMode::PESSIMISTIC_WRITE);
            if ($record instanceof TraceBalance || $record instanceof Movement || $record instanceof Balance) {
                $this->invalid('Stock ledgers are immutable.');
            }
            if ($record instanceof Document) {
                if (!empty($record->details['payments']) || !empty($record->details['receipts'])) {
                    $this->invalid('Documents with financial or receipt history cannot be deleted.');
                }
                if ('orders' === $record->kind && 'cancelled' !== $record->status) {
                    $this->transitionOrder($record, 'cancelled');
                }
                if (!in_array($record->status, ['draft', 'cancelled'], true)) {
                    $this->invalid('Cancel the document before deletion.');
                }
            }
            if ($record instanceof Product && $this->em->createQuery('SELECT COUNT(m.id) FROM App\\Entity\\Inventory\\Movement m JOIN m.product p WHERE p = :product OR IDENTITY(p.parent) = :productId')->setParameter('product', $record)->setParameter('productId', $record->id)->getSingleScalarResult()) {
                $this->invalid('Products with stock history cannot be deleted; deactivate instead.');
            }
            if ($record instanceof Product || $record instanceof Location) {
                foreach ($this->em->getRepository(Document::class)->findAll() as $document) {
                    if ($record instanceof Product && array_filter($document->items, fn ($line) => $line['product_id'] === $record->id || ($line['product_variant_id'] ?? null) === $record->id)) {
                        $this->invalid('Product is referenced by a document.');
                    }
                    if ($record instanceof Location && array_intersect([$record->id], array_intersect_key($document->details, array_flip(['location_id', 'from_location_id', 'to_location_id'])))) {
                        $this->invalid('Location is referenced by a document.');
                    }
                }
            }
            $this->em->remove($record);
        });
    }

    public function action(string $resource, int $id, string $action, array $data): object
    {
        return $this->transaction(function () use ($resource, $id, $action, $data) {
            $doc = $this->get($resource, $id);
            if (!$doc instanceof Document) {
                throw new NotFoundHttpException();
            }
            $this->em->refresh($doc, LockMode::PESSIMISTIC_WRITE);
            $this->fields($data, match ($action) {
                'receive' => 'purchase-orders' === $resource ? ['items'] : [], 'return' => ['items'], 'pay', 'refund' => ['amount', 'reference', 'method', 'notes'],
                'reject' => ['reason'], 'ship' => ['tracking_number', 'carrier', 'items', 'shipping_method', 'estimated_arrival'], default => [],
            });
            if ('order-returns' === $resource) {
                if ('approve' === $action) {
                    $this->state($doc, ['requested']);
                    $doc->status = 'approved';
                } elseif ('cancel' === $action) {
                    $this->state($doc, ['requested', 'approved']);
                    $doc->status = 'cancelled';
                } elseif ('receive' === $action) {
                    $this->state($doc, ['approved']);
                    $order = $this->get('orders', $doc->details['order_id']);
                    $this->em->refresh($order, LockMode::PESSIMISTIC_WRITE);
                    $returns = array_map(static fn ($line) => ['id' => $line['order_line_id'], 'quantity' => $line['quantity'], 'receiving_location_id' => $line['receiving_location_id'], 'serials' => $line['serials'], 'batch' => $line['batch']], $doc->items);
                    $this->returnItems($order, ['items' => $returns]);
                    $doc->status = 'received';
                } else {
                    throw new NotFoundHttpException();
                }

                return $doc;
            }
            if (in_array($action, ['submit', 'approve', 'reject'], true)) {
                if (!in_array($doc->status, ['draft', 'pending', 'processing'], true)) {
                    $this->invalid('Document cannot be approved in its current state.');
                }
                $approval = $doc->details['approval_status'] ?? 'none';
                if ('submit' === $action) {
                    if (!in_array($approval, ['none', 'rejected'], true)) {
                        $this->invalid('Document already submitted.');
                    }
                    $doc->details['approval_required'] = true;
                    $doc->details['approval_status'] = 'pending';
                } else {
                    if ('pending' !== $approval) {
                        $this->invalid('Document is not awaiting approval.');
                    }
                    $doc->details['approval_status'] = 'approve' === $action ? 'approved' : 'rejected';
                    if (isset($data['reason'])) {
                        $doc->details['approval_reason'] = $data['reason'];
                    }
                    if ('reject' === $action && 'orders' === $resource) {
                        $this->transitionOrder($doc, 'cancelled');
                    }
                }

                return $doc;
            }
            if (!empty($doc->details['approval_required']) && ($doc->details['approval_status'] ?? 'pending') !== 'approved' && !in_array($action, ['cancel', 'refund'], true)) {
                $this->invalid('Document requires approval.');
            }
            if ('orders' === $resource) {
                if (in_array($action, ['pay', 'refund'], true)) {
                    $this->payment($doc, $action, $data);

                    return $doc;
                }
                if ('return' === $action) {
                    $this->returnItems($doc, $data);

                    return $doc;
                }
                if ('ship' === $action) {
                    $this->shipOrder($doc, $data);

                    return $doc;
                }
                if ('revert-shipment' === $action) {
                    $this->state($doc, ['shipped']);
                    foreach ($doc->items as $line) {
                        if (!empty($line['returned_quantity'])) {
                            $this->invalid('Cannot revert shipment after returns.');
                        }
                    }
                    $doc->items = array_map(static fn ($line) => array_replace($line, ['shipped_quantity' => 0, 'shipped_serials' => []]), $doc->items);
                    $doc->status = 'processing';

                    return $doc;
                }
                $target = match ($action) {
                    'cancel' => 'cancelled', 'ship' => 'shipped', 'deliver' => 'delivered', 'process' => 'processing', default => throw new NotFoundHttpException(),
                };
                $this->transitionOrder($doc, $target);
            } elseif ('purchase-orders' === $resource) {
                if ('send' === $action) {
                    $this->state($doc, ['draft']);
                    $doc->status = 'sent';
                } elseif ('cancel' === $action) {
                    $this->state($doc, ['draft', 'sent', 'partial']);
                    $doc->status = 'cancelled';
                } elseif ('receive' === $action) {
                    $this->receive($doc, $data);
                } else {
                    throw new NotFoundHttpException();
                }
            } elseif ('stock-audits' === $resource) {
                $this->state($doc, ['draft']);
                if ('cancel' === $action) {
                    $doc->status = 'cancelled';
                } elseif ('complete' === $action) {
                    if (!empty($doc->details['count_required']) || !empty($doc->details['count_history'])) {
                        foreach ($doc->items as $line) {
                            if (empty($doc->details['count_reviews'][(string) $line['id']]) || $doc->details['count_reviews'][(string) $line['id']] !== hash('sha256', json_encode($line, JSON_THROW_ON_ERROR))) {
                                $this->invalid('Every count line must be counted and reviewed before completion.');
                            }
                        }
                    }
                    $location = $this->get('locations', $doc->details['location_id']);
                    // Validate every snapshot before the first reconciliation changes stock.
                    foreach ($doc->items as $line) {
                        $product = $this->stockProduct($line);
                        $this->em->lock($product, LockMode::PESSIMISTIC_WRITE);
                        $balance = $this->ledger->balance($product, $location);
                        if ($balance) {
                            $this->em->refresh($balance, LockMode::PESSIMISTIC_WRITE);
                        }
                        $latest = $this->ledger->latestMovement($product, $location);
                        if ($latest !== $line['expected_movement'] || ($balance?->quantity ?? 0) !== $line['expected_balance']) {
                            $this->invalid('Stock changed since count started; create a new count.');
                        }
                    }
                    foreach ($doc->items as $line) {
                        $product = $this->stockProduct($line);
                        $mode = $product->details['tracking'] ?? $product->parent?->details['tracking'] ?? 'none';
                        if ('serial' === $mode) {
                            $lost = array_values(array_diff($line['expected_serials'], $line['serials']));
                            $found = array_values(array_diff($line['serials'], $line['expected_serials']));
                            if ($lost) {
                                $this->ledger->move($product, $location, -count($lost), 'count', $doc->number, $doc->details['notes'] ?? '', array_replace($line, ['serials' => $lost]));
                            }
                            if ($found) {
                                $this->ledger->move($product, $location, count($found), 'count', $doc->number, $doc->details['notes'] ?? '', array_replace($line, ['serials' => $found]));
                            }
                        } else {
                            $difference = $line['quantity'] - $line['expected_quantity'];
                            if ($difference) {
                                $this->ledger->move($product, $location, $difference, 'count', $doc->number, $doc->details['notes'] ?? '', $line);
                            }
                        }
                    }
                    $doc->status = 'completed';
                } else {
                    throw new NotFoundHttpException();
                }
            } else {
                $from = $this->get('locations', $doc->details['from_location_id']);
                $to = $this->get('locations', $doc->details['to_location_id']);
                switch ($action) {
                    case 'prepare':
                        $this->state($doc, ['draft']);
                        $quantities = [];
                        foreach ($doc->items as $line) {
                            $quantities[$line['product_variant_id'] ?? $line['product_id']] = ($quantities[$line['product_variant_id'] ?? $line['product_id']] ?? 0) + $line['quantity'];
                        }
                        ksort($quantities);
                        foreach ($quantities as $productId => $quantity) {
                            $product = $this->getProduct($productId);
                            $this->em->lock($product, LockMode::PESSIMISTIC_WRITE);
                            $balance = $this->ledger->balance($product, $from);
                            if ($balance) {
                                $this->em->refresh($balance, LockMode::PESSIMISTIC_WRITE);
                            }
                            if ($quantity > ($balance?->quantity ?? 0) - $this->ledger->reserved($product, $from)) {
                                $this->invalid('Insufficient available stock.');
                            }
                        }
                        foreach ($doc->items as $line) {
                            $this->ledger->trace($this->stockProduct($line), $from, -$line['quantity'], $line, $doc->number, true);
                        }
                        $doc->status = 'prepared';
                        break;
                    case 'ship':
                        if ('draft' === $doc->status) {
                            $this->action($resource, $id, 'prepare', []);
                        }
                        $this->state($doc, ['prepared']);
                        $doc->details = array_replace($doc->details, $data);
                        $doc->items = $this->traceDetails($doc->items, $from);
                        foreach ($doc->items as $line) {
                            $this->ledger->move($this->stockProduct($line), $from, -$line['quantity'], 'transfer_out', $doc->number, '', $line);
                        }
                        $doc->status = 'shipped';
                        break;
                    case 'complete':
                        if (in_array($doc->status, ['draft', 'prepared'], true)) {
                            $this->action($resource, $id, 'ship', []);
                        }
                        $this->state($doc, ['shipped']);
                        foreach ($doc->items as $line) {
                            $this->ledger->move($this->stockProduct($line), $to, $line['quantity'], 'transfer_in', $doc->number, '', $line);
                        }
                        $doc->status = 'completed';
                        break;
                    case 'cancel': $this->state($doc, ['draft', 'prepared']);
                        $doc->status = 'cancelled';
                        break;
                    default: throw new NotFoundHttpException();
                }
            }

            return $doc;
        });
    }

    private function receive(Document $doc, array $data): void
    {
        $this->state($doc, ['sent', 'partial']);
        if (empty($data['items'])) {
            $this->invalid('Receipt items are required.');
        }
        $items = $doc->items;
        $seen = [];
        foreach ($data['items'] as $receipt) {
            if (!is_array($receipt)) {
                $this->invalid('Invalid receipt.');
            }
            $this->fields($receipt, ['id', 'quantity_to_receive', 'receiving_location_id', 'batch', 'serials', 'expires_at']);
            $id = $this->positive($receipt, 'id');
            if (isset($seen[$id]) || !isset($items[$id - 1])) {
                $this->invalid('Unknown or duplicate receipt line.');
            }
            $seen[$id] = true;
            $qty = $this->positive($receipt, 'quantity_to_receive');
            $line = &$items[$id - 1];
            if ($qty > $line['quantity'] - $line['received_quantity']) {
                $this->invalid('Receipt exceeds outstanding quantity.');
            }
            $location = $this->get('locations', $this->positive($receipt, 'receiving_location_id'));
            $this->ledger->move($this->stockProduct($line), $location, $qty, 'purchase', $doc->number, '', $receipt);
            $line['received_quantity'] += $qty;
            unset($line);
        }
        $doc->items = $items;
        $doc->details['receipts'][] = ['items' => $data['items'], 'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM)];
        $doc->status = array_filter($items, fn ($line) => $line['quantity'] !== $line['received_quantity']) ? 'partial' : 'received';
    }

    private function transitionOrder(Document $doc, string $target): void
    {
        if ($doc->status === $target) {
            return;
        }
        $allowed = ['pending' => ['processing', 'cancelled'], 'processing' => ['shipped', 'cancelled'], 'shipped' => ['delivered']];
        if (!in_array($target, $allowed[$doc->status] ?? [], true)) {
            $this->invalid('Invalid order status transition.');
        }
        if (in_array($target, ['shipped', 'delivered'], true) && !empty($doc->details['approval_required']) && ($doc->details['approval_status'] ?? '') !== 'approved') {
            $this->invalid('Document requires approval.');
        }
        if ('shipped' === $target) {
            $this->shipOrder($doc, []);

            return;
        }
        if ('cancelled' === $target) {
            foreach ($doc->items as $line) {
                if (!empty($line['shipped_quantity'])) {
                    $this->invalid('Cannot cancel a partially shipped order.');
                }
            }
            $location = $this->get('locations', $doc->details['location_id']);
            foreach ($doc->items as $line) {
                $this->ledger->move($this->stockProduct($line), $location, $line['quantity'], 'return', $doc->number, '', $line);
            }
        }
        $doc->status = $target;
    }

    private function countLines(array $data): array
    {
        $location = $this->get('locations', $this->positive($data, 'location_id'));
        $lines = $data['items'] ?? [];
        if (!$lines || !array_is_list($lines) || count($lines) > 500) {
            $this->invalid('Between 1 and 500 count lines are required.');
        }
        $result = [];
        $seen = [];
        usort($lines, static fn ($a, $b) => (is_array($a) ? ($a['product_variant_id'] ?? $a['product_id'] ?? 0) : 0) <=> (is_array($b) ? ($b['product_variant_id'] ?? $b['product_id'] ?? 0) : 0));
        foreach ($lines as $line) {
            if (!is_array($line)) {
                $this->invalid('Invalid count line.');
            }
            $this->fields($line, ['product_id', 'product_variant_id', 'quantity', 'batch', 'serials', 'expires_at']);
            $product = $this->stockProduct($line);
            $batch = $line['batch'] ?? '';
            if (!is_string($batch) || strlen($batch) > 100) {
                $this->invalid('Invalid count batch.');
            }
            $this->em->lock($product, LockMode::PESSIMISTIC_WRITE);
            $key = $product->id.':'.$batch;
            if (isset($seen[$key])) {
                $this->invalid('Duplicate count product and batch.');
            }
            if (isset($line['product_variant_id']) && $product->parent?->id !== ($line['product_id'] ?? null)) {
                $this->invalid('Invalid variant.');
            }
            $seen[$key] = true;
            $quantity = $this->integer($line, 'quantity');
            if ($quantity < 0) {
                $this->invalid('Count quantity cannot be negative.');
            }
            $balance = $this->ledger->balance($product, $location);
            $latest = $this->ledger->latestMovement($product, $location);
            $expected = $balance?->quantity ?? 0;
            $serials = [];
            $mode = $product->details['tracking'] ?? $product->parent?->details['tracking'] ?? 'none';
            if ('none' !== $mode) {
                if ('batch' === $mode && '' === trim($batch)) {
                    $this->invalid('Batch is required for a batch count.');
                }
                $traces = $this->ledger->traceBalances($product, $location, $batch);
                $expected = 0;
                foreach ($traces as $trace) {
                    $expected += $trace->quantity;
                    if ('' !== $trace->serial && $trace->quantity > 0) {
                        $serials[] = $trace->serial;
                    }
                }
                if ('serial' === $mode) {
                    $counted = $line['serials'] ?? [];
                    if (!is_array($counted) || !array_is_list($counted) || count($counted) !== $quantity || array_filter($counted, static fn ($serial) => !is_string($serial) || '' === $serial || strlen($serial) > 100) || count(array_unique($counted)) !== count($counted)) {
                        $this->invalid('One unique serial is required per counted unit.');
                    }
                }
            }
            $result[] = $line + ['id' => count($result) + 1, 'expected_quantity' => $expected, 'expected_balance' => $balance?->quantity ?? 0, 'expected_movement' => $latest, 'expected_serials' => $serials, 'unit_price' => '0.00'];
        }
        usort($result, fn ($a, $b) => ($a['product_variant_id'] ?? $a['product_id']) <=> ($b['product_variant_id'] ?? $b['product_id']));

        return $result;
    }

    private function returnRequestLines(array $data): array
    {
        $order = $this->get('orders', $this->positive($data, 'order_id'));
        $this->em->refresh($order, LockMode::PESSIMISTIC_WRITE);
        $this->state($order, ['processing', 'shipped', 'delivered']);
        $requests = $data['items'] ?? [];
        if (!$requests || !array_is_list($requests) || count($requests) > 500) {
            $this->invalid('Return request lines are required.');
        }
        $pending = [];
        foreach ($this->em->getRepository(Document::class)->findBy(['kind' => 'order-returns', 'status' => ['requested', 'approved']]) as $request) {
            if ($request->details['order_id'] !== $order->id) {
                continue;
            }
            foreach ($request->items as $line) {
                $pending[$line['order_line_id']] = ($pending[$line['order_line_id']] ?? 0) + $line['quantity'];
            }
        }
        $result = [];
        $seen = [];
        foreach ($requests as $request) {
            if (!is_array($request)) {
                $this->invalid('Invalid return request line.');
            }
            $this->fields($request, ['id', 'quantity', 'receiving_location_id', 'serials', 'batch']);
            $id = $this->positive($request, 'id');
            if (isset($seen[$id]) || !isset($order->items[$id - 1])) {
                $this->invalid('Unknown or duplicate order line.');
            }
            $seen[$id] = true;
            $line = $order->items[$id - 1];
            $quantity = $this->positive($request, 'quantity');
            if ($quantity > ($line['shipped_quantity'] ?? (in_array($order->status, ['shipped', 'delivered'], true) ? $line['quantity'] : 0)) - ($line['returned_quantity'] ?? 0) - ($pending[$id] ?? 0)) {
                $this->invalid('Return request exceeds available shipped quantity.');
            }
            $location = $this->get('locations', $this->positive($request, 'receiving_location_id'));
            $result[] = ['id' => count($result) + 1, 'order_line_id' => $id, 'product_id' => $line['product_id'], 'product_variant_id' => $line['product_variant_id'] ?? null, 'quantity' => $quantity, 'receiving_location_id' => $location->id, 'unit_price' => $line['unit_price'], 'serials' => $request['serials'] ?? [], 'batch' => $request['batch'] ?? $line['batch'] ?? ''];
        }

        return $result;
    }

    public function supplierPrices(int $supplierId, int $page, int $limit): array
    {
        $supplier = $this->get('suppliers', $supplierId);
        $repo = $this->em->getRepository(Document::class);
        $criteria = ['kind' => 'purchase-orders', 'supplier' => $supplier];
        $documents = $repo->findBy($criteria, ['id' => 'DESC'], $limit, ($page - 1) * $limit);
        $data = [];
        foreach ($documents as $document) {
            $data[] = ['purchase_order_id' => $document->id, 'number' => $document->number, 'status' => $document->status, 'currency' => $document->details['currency'] ?? null, 'created_at' => $document->createdAt->format(DATE_ATOM), 'items' => array_map(static fn ($line) => ['product_id' => $line['product_id'], 'product_variant_id' => $line['product_variant_id'] ?? null, 'unit_cost' => $line['unit_price'], 'quantity' => $line['quantity'], 'received_quantity' => $line['received_quantity'], 'supplier_sku' => $line['supplier_sku'] ?? ''], $document->items)];
        }

        return ['data' => $data, 'meta' => ['current_page' => $page, 'per_page' => $limit, 'total' => $repo->count($criteria)]];
    }

    private function shipOrder(Document $doc, array $data): void
    {
        $this->state($doc, ['processing']);
        $items = $doc->items;
        $shipments = $data['items'] ?? array_map(static fn ($line) => ['id' => $line['id'], 'quantity' => $line['quantity'] - ($line['shipped_quantity'] ?? 0)], array_values(array_filter($items, static fn ($line) => $line['quantity'] > ($line['shipped_quantity'] ?? 0))));
        if (!$shipments || !array_is_list($shipments) || count($shipments) > 500) {
            $this->invalid('Shipment lines are required.');
        }
        $seen = [];
        $resolvedShipments = [];
        foreach ($shipments as $shipment) {
            if (!is_array($shipment)) {
                $this->invalid('Invalid shipment line.');
            }
            $this->fields($shipment, ['id', 'quantity', 'serials']);
            $id = $this->positive($shipment, 'id');
            if (!isset($items[$id - 1]) || isset($seen[$id])) {
                $this->invalid('Unknown or duplicate shipment line.');
            }
            $seen[$id] = true;
            $line = &$items[$id - 1];
            $quantity = $this->positive($shipment, 'quantity');
            if ($quantity > $line['quantity'] - ($line['shipped_quantity'] ?? 0)) {
                $this->invalid('Shipment exceeds outstanding quantity.');
            }
            if (!empty($line['serials'])) {
                $remainingSerials = array_values(array_diff($line['serials'], $line['shipped_serials'] ?? []));
                $serials = $shipment['serials'] ?? array_slice($remainingSerials, 0, $quantity);
                if (!is_array($serials) || count($serials) !== $quantity || array_filter($serials, static fn ($serial) => !is_string($serial)) || count(array_unique($serials)) !== count($serials) || array_diff($serials, $remainingSerials)) {
                    $this->invalid('Shipment serials must identify unshipped order units.');
                }
                $line['shipped_serials'] = [...($line['shipped_serials'] ?? []), ...$serials];
                $shipment['serials'] = $serials;
            }
            $line['shipped_quantity'] = ($line['shipped_quantity'] ?? 0) + $quantity;
            $resolvedShipments[] = $shipment;
            unset($line);
        }
        $doc->items = $items;
        $doc->details['shipments'][] = ['items' => $resolvedShipments, 'tracking_number' => $data['tracking_number'] ?? '', 'carrier' => $data['carrier'] ?? '', 'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM)];
        $doc->status = array_filter($items, static fn ($line) => $line['quantity'] !== ($line['shipped_quantity'] ?? 0)) ? 'processing' : 'shipped';
    }

    private function payment(Document $doc, string $action, array $data): void
    {
        $amount = \Brick\Math\BigDecimal::of($this->money($data['amount'] ?? '0'));
        if (!$amount->isPositive()) {
            $this->invalid('Payment amount must be positive.');
        }
        $payments = $doc->details['payments'] ?? [];
        $net = \Brick\Math\BigDecimal::of('0.00');
        foreach ($payments as $payment) {
            if (!empty($data['reference']) && $payment['reference'] === $data['reference']) {
                $this->invalid('Duplicate payment reference.');
            }
            $net = 'pay' === $payment['type'] ? $net->plus($payment['amount']) : $net->minus($payment['amount']);
        }
        if ('refund' === $action && $amount->isGreaterThan($net)) {
            $this->invalid('Refund exceeds payments received.');
        }
        if ('pay' === $action && 'cancelled' === $doc->status) {
            $this->invalid('Cannot pay a cancelled order.');
        }
        $payments[] = ['id' => count($payments) + 1, 'type' => $action, 'amount' => (string) $amount, 'reference' => $data['reference'] ?? '', 'method' => $data['method'] ?? '', 'notes' => $data['notes'] ?? '', 'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM)];
        $doc->details['payments'] = $payments;
    }

    private function returnItems(Document $doc, array $data): void
    {
        $this->state($doc, ['processing', 'shipped', 'delivered']);
        $returns = $data['items'] ?? [];
        if (!$returns || !array_is_list($returns) || count($returns) > 500) {
            $this->invalid('Return lines are required.');
        }
        $seen = [];
        $items = $doc->items;
        foreach ($returns as $return) {
            if (!is_array($return)) {
                $this->invalid('Invalid return line.');
            }
            $this->fields($return, ['id', 'quantity', 'receiving_location_id', 'batch', 'serials', 'expires_at']);
            $id = $this->positive($return, 'id');
            if (isset($seen[$id]) || !isset($items[$id - 1])) {
                $this->invalid('Unknown or duplicate return line.');
            }
            $seen[$id] = true;
            $line = &$items[$id - 1];
            $quantity = $this->positive($return, 'quantity');
            if ($quantity > ($line['shipped_quantity'] ?? (in_array($doc->status, ['shipped', 'delivered'], true) ? $line['quantity'] : 0)) - ($line['returned_quantity'] ?? 0)) {
                $this->invalid('Return exceeds shipped quantity.');
            }
            $serials = $return['serials'] ?? [];
            if (!is_array($serials) || array_diff($serials, $line['shipped_serials'] ?? $line['serials'] ?? []) || array_intersect($serials, $line['returned_serials'] ?? [])) {
                $this->invalid('Returned serial was not shipped or was already returned.');
            }
            if (isset($return['batch']) && $return['batch'] !== ($line['batch'] ?? '')) {
                $this->invalid('Returned batch differs from shipment.');
            }
            $line['returned_serials'] = [...($line['returned_serials'] ?? []), ...$serials];
            $location = $this->get('locations', $this->positive($return, 'receiving_location_id'));
            $this->ledger->move($this->stockProduct($line), $location, $quantity, 'return', $doc->number, '', $return + $line);
            $line['returned_quantity'] = ($line['returned_quantity'] ?? 0) + $quantity;
            unset($line);
        }
        $doc->items = $items;
    }

    private function traceDetails(array $lines, Location $location): array
    {
        foreach ($lines as &$line) {
            if (!empty($line['expires_at'])) {
                continue;
            }
            $product = $this->stockProduct($line);
            if (($product->details['tracking'] ?? $product->parent?->details['tracking'] ?? 'none') === 'none') {
                continue;
            }
            $this->em->lock($product, LockMode::PESSIMISTIC_WRITE);
            $expiries = [];
            foreach ($this->ledger->traceBalances($product, $location, $line['batch'] ?? '') as $trace) {
                if ($trace->quantity <= 0 || ('' !== $trace->serial && !in_array($trace->serial, $line['serials'] ?? [], true))) {
                    continue;
                }
                $expiries[$trace->expiresAt?->format('Y-m-d') ?? ''] = true;
            }
            if (count($expiries) > 1) {
                $this->invalid('Split units with different expiry dates into separate lines.');
            }
            if ($expiries) {
                $line['expires_at'] = array_key_first($expiries) ?: null;
            }
        }
        unset($line);

        return $lines;
    }

    private function stockProduct(array $line): Product
    {
        return $this->getProduct(isset($line['product_variant_id']) ? $this->positive($line, 'product_variant_id') : $this->positive($line, 'product_id'));
    }

    private function lines(array $lines, string $resource = 'orders'): array
    {
        if (!$lines || count($lines) > 500 || !array_is_list($lines)) {
            $this->invalid('Between 1 and 500 line items are required.');
        }
        $result = [];
        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                $this->invalid('Invalid line item.');
            }
            $this->fields($line, ['product_id', 'quantity', 'unit_price', 'unit_cost', 'product_variant_id', 'tax', 'discount_type', 'discount_value', 'supplier_sku', 'batch', 'serials', 'expires_at']);

            $product = $this->get('products', $this->positive($line, 'product_id'));
            $variant = null;
            if (isset($line['product_variant_id'])) {
                $variant = $this->get('variants', $this->positive($line, 'product_variant_id'));
                if ($variant->parent?->id !== $product->id || !$variant->isActive) {
                    $this->invalid('Invalid product variant.');
                }
            }
            if (!$product->isActive) {
                $this->invalid('Inactive product.');
            }
            if (isset($line['supplier_sku']) && (!is_string($line['supplier_sku']) || strlen($line['supplier_sku']) > 255)) {
                $this->invalid('Invalid supplier SKU.');
            }
            if (isset($line['serials']) && (!is_array($line['serials']) || !array_is_list($line['serials']) || array_filter($line['serials'], static fn ($serial) => !is_string($serial) || strlen($serial) > 100))) {
                $this->invalid('Invalid serials.');
            }
            if (isset($line['batch']) && (!is_string($line['batch']) || strlen($line['batch']) > 100)) {
                $this->invalid('Invalid batch.');
            }
            $result[] = ['batch' => $line['batch'] ?? '', 'serials' => $line['serials'] ?? [], 'expires_at' => $line['expires_at'] ?? null, 'id' => $i + 1, 'product_id' => $product->id, 'product_variant_id' => $variant?->id, 'quantity' => $this->positive($line, 'quantity'), 'received_quantity' => 0, 'tax' => $this->money($line['tax'] ?? '0'), 'discount_type' => $line['discount_type'] ?? 'fixed', 'discount_value' => $this->money($line['discount_value'] ?? '0'), 'supplier_sku' => $line['supplier_sku'] ?? '', 'unit_price' => $this->money($line['unit_price'] ?? $line['unit_cost'] ?? ('purchase-orders' === $resource ? $product->cost : $product->price))];
        }

        return $result;
    }

    private function editMaster(object $record, array $data): void
    {
        $allowed = $record instanceof Product ? ['name', 'sku', 'description', 'price', 'cost', 'is_active', 'category_id', 'barcode', 'min_stock', 'options', 'attributes', 'unit', 'metadata', 'tracking', 'reorder_point', 'reorder_quantity', 'max_stock'] : ($record instanceof Location ? ['name', 'code', 'warehouse_id', 'parent_id', 'aisle', 'shelf', 'bin'] : (($record instanceof Category || $record instanceof Warehouse) ? ($record instanceof Category ? ['name', 'parent_id', 'description', 'notes', 'metadata'] : ['name', 'address', 'city', 'state', 'zip_code', 'country', 'phone', 'email', 'notes', 'metadata']) : ['name', 'email', 'phone', 'company_name', 'contact_name', 'billing_address', 'shipping_address', 'address', 'city', 'country', 'tax_id', 'payment_terms', 'currency', 'notes', 'is_active', 'code', 'state', 'zip_code', 'website', 'rating', 'metadata', 'billing_city', 'billing_state', 'billing_zip_code', 'billing_country', 'shipping_city', 'shipping_state', 'shipping_zip_code', 'shipping_country', 'credit_limit']));
        if (array_diff(array_keys($data), $allowed)) {
            $this->invalid('Unsupported fields: '.implode(', ', array_diff(array_keys($data), $allowed)));
        }
        foreach ($data as $field => $value) {
            $property = 'is_active' === $field ? 'isActive' : $field;
            if ('code' === $field && ($record instanceof Customer || $record instanceof Supplier)) {
                if (!is_string($value) || strlen($value) > 255) {
                    $this->invalid('Invalid contact code.');
                }
                $record->code = trim($value) ?: null;
            } elseif ('credit_limit' === $field) {
                $record->details[$field] = $this->money($value);
            } elseif (in_array($field, ['category_id', 'warehouse_id', 'parent_id'], true)) {
                $resource = match ($field) {
                    'category_id' => 'categories', 'warehouse_id' => 'warehouses', default => $record instanceof Category ? 'categories' : 'locations',
                };
                $target = $this->get($resource, $this->positive($data, $field));
                if ('parent_id' === $field) {
                    for ($ancestor = $target; $ancestor; $ancestor = $ancestor->parent) {
                        if ($ancestor === $record) {
                            $this->invalid('Hierarchy cannot contain a cycle.');
                        }
                    }
                }
                $property = substr($field, 0, -3);
                $record->$property = $target;
            } elseif (in_array($field, ['reorder_point', 'reorder_quantity', 'max_stock'], true)) {
                $value = $this->integer($data, $field);
                if ($value < 0) {
                    $this->invalid($field.' cannot be negative.');
                }
                $record->details[$field] = $value;
            } elseif ('min_stock' === $field) {
                $value = $this->integer($data, $field);
                if ($value < 0) {
                    $this->invalid('min_stock cannot be negative.');
                }
                $record->minStock = $value;
            } elseif (in_array($field, ['options', 'attributes'], true)) {
                if (!is_array($value)) {
                    $this->invalid($field.' must be an array.');
                }
                if ('options' === $field) {
                    foreach ($value as $option => $values) {
                        if (!is_string($option) || !is_array($values) || !$values || !array_is_list($values) || array_filter($values, static fn ($v) => !is_string($v) || '' === $v)) {
                            $this->invalid('Options must map names to nonempty lists of strings.');
                        }
                    }
                    if ($record->id && $this->em->getRepository(Product::class)->count(['parent' => $record]) && ($record->details['options'] ?? []) !== $value) {
                        $this->invalid('Options with existing variants cannot be redefined.');
                    }
                }
                $record->details[$field] = $value;
            } elseif ('tracking' === $field) {
                if (!in_array($value, ['none', 'batch', 'serial'], true)) {
                    $this->invalid('Invalid tracking mode.');
                }
                if ($record->id && ($record->details['tracking'] ?? 'none') !== $value && $this->em->createQuery('SELECT COUNT(m.id) FROM App\\Entity\\Inventory\\Movement m JOIN m.product p WHERE p = :product OR IDENTITY(p.parent) = :productId')->setParameter('product', $record)->setParameter('productId', $record->id)->getSingleScalarResult()) {
                    $this->invalid('Tracking cannot change after stock movements.');
                }
                $record->details[$field] = $value;
            } elseif ('isActive' === $property) {
                if (!is_bool($value)) {
                    $this->invalid('is_active must be boolean.');
                } $record->isActive = $value;
            } elseif (property_exists($record, $property)) {
                if (!is_string($value) || strlen($value) > ('description' === $property ? 10000 : 255)) {
                    $this->invalid('Invalid '.$field.'.');
                }
                $record->$property = in_array($field, ['price', 'cost'], true) ? $this->money($value) : trim($value);
            } elseif ('metadata' === $field) {
                if (!is_array($value)) {
                    $this->invalid('metadata must be an object.');
                }
                $record->details[$field] = $value;
            } elseif ('rating' === $field) {
                if (!is_int($value) || $value < 1 || $value > 5) {
                    $this->invalid('rating must be between 1 and 5.');
                }
                $record->details[$field] = $value;
            } else {
                if (!is_string($value)) {
                    $this->invalid('Invalid '.$field.'.');
                } $record->details[$field] = $value;
            }
        }
        if ($record instanceof Product && isset($record->details['max_stock']) && $record->details['max_stock'] < ($record->details['reorder_point'] ?? $record->minStock)) {
            $this->invalid('max_stock must be at least the reorder point.');
        }
        if (property_exists($record, 'details')) {
            $this->validateDetails($record->details);
        }
        if ($record instanceof Product && $record->parent) {
            $this->em->lock($record->parent, LockMode::PESSIMISTIC_WRITE);
            foreach ($this->em->getRepository(Product::class)->findBy(['parent' => $record->parent]) as $sibling) {
                if ($sibling === $record) {
                    continue;
                }
                $attributes = $record->details['attributes'] ?? [];
                $other = $sibling->details['attributes'] ?? [];
                ksort($attributes);
                ksort($other);
                if ($attributes === $other) {
                    $this->invalid('A variant with these attributes already exists.');
                }
            }
        }
        if ($record instanceof Product && $record->parent) {
            $options = $record->parent->details['options'] ?? [];
            $attributes = $record->details['attributes'] ?? [];
            if (array_diff(array_keys($attributes), array_keys($options)) || array_diff(array_keys($options), array_keys($attributes))) {
                $this->invalid('Variant attributes must match the parent options.');
            }
            foreach ($attributes as $option => $value) {
                if (!is_string($value) || !in_array($value, $options[$option], true)) {
                    $this->invalid('Invalid variant option value.');
                }
            }
        }
        if ('' === $record->name) {
            $this->invalid('name is required.');
        }
        if ($record instanceof Product && ('' === $record->sku || strlen($record->sku) > 100)) {
            $this->invalid('sku is required and must be at most 100 characters.');
        }
        if ($record instanceof Location && ('' === $record->code || strlen($record->code) > 100)) {
            $this->invalid('code is required and must be at most 100 characters.');
        }
        if (property_exists($record, 'email') && '' !== $record->email && !filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
            $this->invalid('Invalid email.');
        }
        if ($record instanceof Product) {
            $record->price = $this->money($record->price ?: '0');
            $record->cost = $this->money($record->cost ?: '0');
        }
    }

    private function validateDetails(array $data): void
    {
        foreach (['order_date', 'expected_date'] as $field) {
            if (!isset($data[$field])) {
                continue;
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data[$field]);
            if (!$date || $date->format('Y-m-d') !== $data[$field]) {
                $this->invalid($field.' must be YYYY-MM-DD.');
            }
        }
        if (isset($data['order_date'], $data['expected_date']) && $data['expected_date'] < $data['order_date']) {
            $this->invalid('expected_date precedes order_date.');
        }
        if (isset($data['currency']) && !preg_match('/^[A-Z]{3}$/D', $data['currency'])) {
            $this->invalid('currency must be a three-letter uppercase code.');
        }
        if (!empty($data['customer_email']) && !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $this->invalid('Invalid customer email.');
        }
        if (!empty($data['website']) && (!filter_var($data['website'], FILTER_VALIDATE_URL) || !in_array(parse_url($data['website'], PHP_URL_SCHEME), ['http', 'https'], true))) {
            $this->invalid('Invalid website.');
        }
    }

    private function fields(array $data, array $allowed): void
    {
        $unknown = array_diff(array_keys($data), $allowed);
        if ($unknown) {
            $this->invalid('Unsupported fields: '.implode(', ', $unknown));
        }
    }

    private function money(mixed $value): string
    {
        return $this->totals->money($value);
    }

    private function integer(array $data, string $field): int
    {
        if (!isset($data[$field]) || !is_int($data[$field]) || abs($data[$field]) > 1000000000) {
            $this->invalid($field.' must be an integer within +/- 1000000000.');
        }

        return $data[$field];
    }

    private function positive(array $data, string $field): int
    {
        $n = $this->integer($data, $field);
        if ($n < 1) {
            $this->invalid($field.' must be positive.');
        }

        return $n;
    }

    private function state(Document $doc, array $allowed): void
    {
        if (!in_array($doc->status, $allowed, true)) {
            $this->invalid('Action is not allowed in status '.$doc->status.'.');
        }
    }

    private function invalid(string $message): never
    {
        throw new UnprocessableEntityHttpException($message);
    }

    public function view(object $record): array
    {
        $result = [];
        foreach (get_object_vars($record) as $key => $value) {
            $key = strtolower(preg_replace('/[A-Z]/', '_$0', $key));
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            } elseif (null === $value && in_array($key, ['parent', 'category', 'warehouse', 'customer', 'supplier', 'product', 'location'], true)) {
                $key .= '_id';
            } elseif (is_object($value)) {
                $key .= '_id';
                $value = $value->id;
            }
            $result[$key] = $value;
        }
        if ($record instanceof Balance) {
            $result['reserved_quantity'] = $this->ledger->reserved($record->product, $record->location);
            $result['available_quantity'] = $record->quantity - $result['reserved_quantity'];
        }
        if ($record instanceof Product) {
            $result['stock'] = (int) $this->em->createQuery('SELECT SUM(b.quantity) FROM App\\Entity\\Inventory\\Balance b JOIN b.product p WHERE p = :product OR IDENTITY(p.parent) = :productId')->setParameter('product', $record)->setParameter('productId', $record->id)->getSingleScalarResult();
        }
        if ($record instanceof Document) {
            $result = array_replace($result, $this->totals->calculate($record->items, $record->details));
            if ('orders' === $record->kind) {
                $paid = \Brick\Math\BigDecimal::of('0.00');
                foreach ($record->details['payments'] ?? [] as $payment) {
                    $paid = 'pay' === $payment['type'] ? $paid->plus($payment['amount']) : $paid->minus($payment['amount']);
                }
                $result['amount_paid'] = (string) $paid;
                $result['balance_due'] = (string) \Brick\Math\BigDecimal::of($result['total'])->minus($paid);
                $result['payment_status'] = $paid->isZero() ? 'unpaid' : ($paid->isLessThan($result['total']) ? 'partial' : ($paid->isEqualTo($result['total']) ? 'paid' : 'overpaid'));
            }
        }

        return $result;
    }
}
