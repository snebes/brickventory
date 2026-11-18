<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\Document;
use App\Entity\Inventory\Movement;
use App\Entity\Inventory\ProductSerial;
use App\Entity\Inventory\TraceBalance;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SerialManagement
{
    use TransactionalInventory;

    public function __construct(private EntityManagerInterface $em, private ManagerRegistry $registry, private InventoryService $inventory, private InventoryStockLedger $ledger)
    {
    }

    public function listing(int $productId, int $page, int $limit, string $search = '', string $status = ''): array
    {
        $p = $this->inventory->getProduct($productId);
        // Old trace rows remain authoritative; expose their history without a destructive backfill.
        $rows = [];
        foreach ($this->em->getRepository(TraceBalance::class)->findBy(['product' => $p]) as $trace) {
            if ('' === $trace->serial) {
                continue;
            }
            $rows[$trace->serial] ??= ['serial_number' => $trace->serial, 'product_id' => $p->id, 'status' => 'unavailable', 'notes' => '', 'locations' => []];
            if ($trace->quantity > 0) {
                $rows[$trace->serial]['status'] = 'available';
                $rows[$trace->serial]['locations'][] = ['location_id' => $trace->location->id, 'location' => $trace->location->name, 'batch' => $trace->batch, 'expires_at' => $trace->expiresAt?->format('Y-m-d')];
            }
        }
        foreach ($this->em->getRepository(ProductSerial::class)->findBy(['product' => $p]) as $serial) {
            $rows[$serial->serialNumber] = array_replace($rows[$serial->serialNumber] ?? ['locations' => []], ['id' => $serial->id, 'serial_number' => $serial->serialNumber, 'product_id' => $p->id, 'status' => $serial->status, 'notes' => $serial->notes]);
        }
        foreach ($this->em->getRepository(Document::class)->findBy(['kind' => 'stock-transfers', 'status' => 'prepared']) as $transfer) {
            foreach ($transfer->items as $line) {
                if (($line['product_variant_id'] ?? $line['product_id']) !== $p->id) {
                    continue;
                }
                foreach ($line['serials'] ?? [] as $number) {
                    if (isset($rows[$number])) {
                        $rows[$number]['status'] = 'reserved';
                        $rows[$number]['reserved_by'] = $transfer->number;
                    }
                }
            }
        }
        $rows = array_values(array_filter($rows, static fn ($r) => ('' === $search || str_contains(strtolower($r['serial_number'].' '.$r['notes']), strtolower($search))) && ('' === $status || $r['status'] === $status)));
        usort($rows, static fn ($a, $b) => strcmp($a['serial_number'], $b['serial_number']));

        return ['data' => array_slice($rows, ($page - 1) * $limit, $limit), 'meta' => ['total' => count($rows), 'current_page' => $page, 'per_page' => $limit]];
    }

    public function save(int $productId, array $data, ?string $number = null): array
    {
        if (array_diff(array_keys($data), null === $number ? ['serial_number', 'notes', 'location_id'] : ['notes', 'status'])) {
            throw new UnprocessableEntityHttpException('Unexpected serial fields.');
        }

        return $this->transaction(function () use ($productId, $data, $number) {
            $p = $this->inventory->getProduct($productId);
            $this->em->refresh($p, LockMode::PESSIMISTIC_WRITE);
            if ('serial' !== ($p->details['tracking'] ?? $p->parent?->details['tracking'] ?? 'none')) {
                throw new UnprocessableEntityHttpException('Product does not use serial tracking.');
            }
            $serialNumber = $number ?? $data['serial_number'] ?? '';
            if ('' === trim($serialNumber) || strlen($serialNumber) > 100 || str_contains($serialNumber, '/')) {
                throw new UnprocessableEntityHttpException('Serial must contain 1–100 characters without a slash.');
            }
            $s = $this->em->getRepository(ProductSerial::class)->createQueryBuilder('s')->where('s.product = :product AND s.serialNumber = :serial')->setParameter('product', $p)->setParameter('serial', $serialNumber)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
            $traces = $this->em->getRepository(TraceBalance::class)->findBy(['product' => $p, 'serial' => $serialNumber]);
            if (null === $number && ($s || $traces)) {
                throw new ConflictHttpException('Serial already exists.');
            }
            if (!$s) {
                if (null !== $number && !$traces) {
                    throw new NotFoundHttpException('Serial not found.');
                }
                $s = new ProductSerial();
                $s->product = $p;
                $s->serialNumber = $serialNumber;
                if (array_filter($traces, static fn ($t) => $t->quantity > 0)) {
                    $s->status = 'available';
                }
                $this->em->persist($s);
                $this->em->flush();
            }
            if (isset($data['location_id'])) {
                $this->ledger->move($p, $this->inventory->get('locations', $data['location_id']), 1, 'manual', 'serial-registration', 'Registered serial', ['serials' => [$serialNumber]]);
            }
            if (isset($data['status']) && $data['status'] !== $s->status) {
                if (!in_array($data['status'], ['available', 'reserved', 'damaged'], true) || !in_array($s->status, ['available', 'reserved', 'damaged'], true)) {
                    throw new UnprocessableEntityHttpException('Only stocked serials can change availability; sales and returns use order workflows.');
                }
                foreach ($this->em->getRepository(Document::class)->findBy(['kind' => 'stock-transfers', 'status' => 'prepared']) as $transfer) {
                    foreach ($transfer->items as $line) {
                        if (($line['product_variant_id'] ?? $line['product_id']) === $p->id && in_array($serialNumber, $line['serials'] ?? [], true)) {
                            throw new ConflictHttpException('Serial is held by a prepared transfer.');
                        }
                    }
                }
                $s->status = $data['status'];
            }
            if (isset($data['notes'])) {
                $s->notes = $data['notes'];
            }
            $this->em->flush();

            return ['id' => $s->id, 'product_id' => $p->id, 'serial_number' => $s->serialNumber, 'status' => $s->status, 'notes' => $s->notes];
        });
    }

    public function history(int $productId, string $number): array
    {
        $p = $this->inventory->getProduct($productId);
        $rows = [];
        foreach ($this->em->getRepository(Movement::class)->findBy(['product' => $p], ['id' => 'DESC']) as $m) {
            if (in_array($number, $m->details['serials'] ?? [], true)) {
                $rows[] = $this->inventory->view($m);
            }
        }

        return $rows;
    }
}
