<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\Balance;
use App\Entity\Inventory\Document;
use App\Entity\Inventory\Location;
use App\Entity\Inventory\Movement;
use App\Entity\Inventory\Product;
use App\Entity\Inventory\ProductSerial;
use App\Entity\Inventory\TraceBalance;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class InventoryStockLedger
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    private function currentQuery(string $class, array $criteria): \Doctrine\ORM\Query
    {
        $qb = $this->registry->getManager()->getRepository($class)->createQueryBuilder('record');
        foreach ($criteria as $field => $value) {
            $qb->andWhere('record.'.$field.' = :'.$field)->setParameter($field, $value);
        }
        $query = $qb->getQuery();
        if ($this->registry->getConnection()->isTransactionActive()) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return $query;
    }

    public function balance(Product $product, Location $location): ?Balance
    {
        return $this->currentQuery(Balance::class, ['product' => $product, 'location' => $location])->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
    }

    public function latestMovement(Product $product, Location $location): int
    {
        $query = $this->currentQuery(Movement::class, ['product' => $product, 'location' => $location]);
        $query->setDQL($query->getDQL().' ORDER BY record.id DESC')->setMaxResults(1);
        $rows = $query->getArrayResult();

        return $rows[0]['id'] ?? 0;
    }

    public function traceBalances(Product $product, Location $location, string $batch): array
    {
        return $this->currentQuery(TraceBalance::class, ['product' => $product, 'location' => $location, 'batch' => $batch])->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getResult();
    }

    public function move(Product $product, Location $location, int $quantity, string $type, string $reference, string $reason = '', array $trace = []): Movement
    {
        // Lock the existing product before looking up a possibly new balance row.
        // This also serializes simultaneous first receipts into the same bin.
        $this->registry->getManager()->lock($product, LockMode::PESSIMISTIC_WRITE);
        $balance = $this->balance($product, $location);
        if (!$balance) {
            $balance = new Balance();
            $balance->product = $product;
            $balance->location = $location;
            $this->registry->getManager()->persist($balance);
        } else {
            $this->registry->getManager()->refresh($balance, LockMode::PESSIMISTIC_WRITE);
        }
        $before = $balance->quantity;
        if ($quantity < 0 && -$quantity > $before - $this->reserved($product, $location, $reference)) {
            $this->invalid('Insufficient available stock for '.$product->sku.'.');
        }
        if ($quantity < -$before) {
            $this->invalid('Insufficient stock for '.$product->sku.'.');
        }
        if ($before + $quantity > 2147483647) {
            $this->invalid('Stock exceeds the supported quantity.');
        }
        $this->trace($product, $location, $quantity, $trace + ['_movement_type' => $type], $reference, false, in_array($type, ['sale', 'transfer_out'], true));
        $balance->quantity += $quantity;
        $movement = new Movement();
        $movement->product = $product;
        $movement->location = $location;
        $movement->quantity = $quantity;
        $movement->quantityBefore = $before;
        $movement->quantityAfter = $balance->quantity;
        $movement->details = array_intersect_key($trace, array_flip(['batch', 'serials', 'expires_at']));
        $movement->type = $type;
        $movement->reference = $reference;
        $movement->reason = $reason;
        $this->registry->getManager()->persist($movement);
        // Make repeated lines in this transaction observe their preceding movement.
        $this->registry->getManager()->flush();

        return $movement;
    }

    public function trace(Product $product, Location $location, int $quantity, array $data, string $exclude = '', bool $validateOnly = false, bool $checkExpiry = true): void
    {
        $mode = $product->details['tracking'] ?? $product->parent?->details['tracking'] ?? 'none';
        if ('none' === $mode) {
            if (!empty($data['batch']) || !empty($data['serials'])) {
                $this->invalid('Product does not use batch or serial tracking.');
            }

            return;
        }
        $batch = $data['batch'] ?? '';
        if (!is_string($batch) || strlen($batch) > 100 || ('batch' === $mode && '' === trim($batch))) {
            $this->invalid('A batch identifier of at most 100 characters is required.');
        }
        $serials = $data['serials'] ?? [];
        if (!is_array($serials) || !array_is_list($serials) || count($serials) > 1000) {
            $this->invalid('Invalid serials.');
        }
        if (array_filter($serials, static fn ($serial) => !is_string($serial))) {
            $this->invalid('Serial identifiers must be strings.');
        }
        if ('serial' === $mode && (count($serials) !== abs($quantity) || count(array_unique($serials, SORT_REGULAR)) !== count($serials))) {
            $this->invalid('One unique serial is required per unit.');
        }
        if ('batch' === $mode && $serials) {
            $this->invalid('Serials are not valid for batch-only products.');
        }
        $expiry = null;
        if (!empty($data['expires_at'])) {
            if (!is_string($data['expires_at'])) {
                $this->invalid('Invalid expiry date.');
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['expires_at']);
            if (!$date || $date->format('Y-m-d') !== $data['expires_at']) {
                $this->invalid('Expiry must be YYYY-MM-DD.');
            }
            $expiry = new \DateTimeImmutable($data['expires_at']);
        }
        foreach ('serial' === $mode ? $serials : [''] as $serial) {
            if (!is_string($serial) || strlen($serial) > 100 || ('serial' === $mode && '' === trim($serial))) {
                $this->invalid('Invalid serial identifier.');
            }
            $delta = 'serial' === $mode ? ($quantity > 0 ? 1 : -1) : $quantity;
            $registration = '' !== $serial ? $this->currentQuery(ProductSerial::class, ['product' => $product, 'serialNumber' => $serial])->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult() : null;
            if ($delta > 0 && $registration && 'sold' === $registration->status && (!in_array($data['_movement_type'] ?? '', ['return', 'order_edit_return'], true) || '' === $exclude)) {
                $this->invalid('Sold serials must be returned through their order.');
            }
            if ($delta < 0 && $registration && in_array($registration->status, ['reserved', 'damaged'], true)) {
                $this->invalid('Serial is reserved or damaged.');
            }
            $repo = $this->registry->getManager()->getRepository(TraceBalance::class);
            if ('' !== $serial && $delta > 0 && count($this->currentQuery(TraceBalance::class, ['product' => $product, 'serial' => $serial, 'quantity' => 1])->getArrayResult())) {
                $this->invalid('Serial is already in stock.');
            }
            $trace = $this->currentQuery(TraceBalance::class, ['product' => $product, 'location' => $location, 'batch' => $batch, 'serial' => $serial])->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
            if (!$trace) {
                $trace = new TraceBalance();
                $trace->product = $product;
                $trace->location = $location;
                $trace->batch = $batch;
                $trace->serial = $serial;
                if (!$validateOnly) {
                    $this->registry->getManager()->persist($trace);
                }
            }
            if ($delta < 0) {
                $reserved = 0;
                foreach ($this->currentQuery(Document::class, ['kind' => 'stock-transfers', 'status' => 'prepared'])->getArrayResult() as $transfer) {
                    if ($transfer['number'] === $exclude || $transfer['details']['from_location_id'] !== $location->id) {
                        continue;
                    }
                    foreach ($transfer['items'] as $line) {
                        if (($line['product_variant_id'] ?? $line['product_id']) !== $product->id || ($line['batch'] ?? '') !== $batch) {
                            continue;
                        }
                        $reserved += 'serial' === $mode ? (in_array($serial, $line['serials'] ?? [], true) ? 1 : 0) : $line['quantity'];
                    }
                }
                if (-$delta > $trace->quantity - $reserved) {
                    $this->invalid('Batch or serial stock is reserved.');
                }
            }
            if ($trace->quantity + $delta < 0) {
                $this->invalid('Insufficient batch or serial stock.');
            }
            if ($checkExpiry && $quantity < 0 && $trace->expiresAt && $trace->expiresAt < new \DateTimeImmutable('today')) {
                $this->invalid('Expired stock cannot be issued.');
            }
            if ($expiry && $trace->expiresAt && $expiry != $trace->expiresAt) {
                $this->invalid('Batch expiry cannot change.');
            }
            $trace->expiresAt ??= $expiry;
            if ($validateOnly) {
                continue;
            }
            if ('' !== $serial) {
                if (!$registration) {
                    $registration = new ProductSerial();
                    $registration->product = $product;
                    $registration->serialNumber = $serial;
                    $this->registry->getManager()->persist($registration);
                }
                $registration->status = $delta > 0 ? 'available' : ('transfer_out' === ($data['_movement_type'] ?? '') ? 'in_transit' : ('sale' === ($data['_movement_type'] ?? '') ? 'sold' : 'unavailable'));
            }
            $trace->quantity += $delta;
            $this->registry->getManager()->flush();
        }
    }

    public function reserved(Product $product, Location $location, string $exclude = ''): int
    {
        $quantity = 0;
        foreach ($this->currentQuery(Document::class, ['kind' => 'stock-transfers', 'status' => 'prepared'])->getArrayResult() as $transfer) {
            if ($transfer['number'] === $exclude || $transfer['details']['from_location_id'] !== $location->id) {
                continue;
            }
            foreach ($transfer['items'] as $line) {
                if (($line['product_variant_id'] ?? $line['product_id']) === $product->id) {
                    $quantity += $line['quantity'];
                }
            }
        }

        foreach ($this->currentQuery(TraceBalance::class, ['product' => $product, 'location' => $location, 'quantity' => 1])->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getResult() as $trace) {
            if ('' === $trace->serial) {
                continue;
            }
            $registration = $this->currentQuery(ProductSerial::class, ['product' => $product, 'serialNumber' => $trace->serial])->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
            if ($registration && in_array($registration->status, ['reserved', 'damaged'], true)) {
                ++$quantity;
            }
        }

        return $quantity;
    }

    private function invalid(string $message): never
    {
        throw new UnprocessableEntityHttpException($message);
    }
}
