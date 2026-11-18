<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\Balance;
use App\Entity\Inventory\CycleCountSchedule;
use App\Entity\Inventory\Document;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class StockCounts
{
    use TransactionalInventory;

    public function __construct(private EntityManagerInterface $em, private ManagerRegistry $registry, private InventoryService $inventory, private InventoryStockLedger $ledger)
    {
    }

    public function count(int $id, int $lineId, array $data, bool $review): array
    {
        if (array_diff(array_keys($data), $review ? [] : ['quantity', 'serials', 'notes'])) {
            throw new UnprocessableEntityHttpException('Unexpected count fields.');
        }

        return $this->transaction(function () use ($id, $lineId, $data, $review) {
            $d = $this->inventory->get('stock-audits', $id);
            $this->em->refresh($d, LockMode::PESSIMISTIC_WRITE);
            if ('draft' !== $d->status) {
                throw new UnprocessableEntityHttpException('Only draft counts can be changed.');
            }
            $index = array_search($lineId, array_column($d->items, 'id'), true);
            if (false === $index) {
                throw new NotFoundHttpException('Count line not found.');
            }
            if ($review) {
                if (empty($d->details['count_history'][(string) $lineId])) {
                    throw new UnprocessableEntityHttpException('Record a count before reviewing it.');
                }
                $d->details['count_reviews'][(string) $lineId] = hash('sha256', json_encode($d->items[$index], JSON_THROW_ON_ERROR));
            } else {
                $qty = $data['quantity'] ?? null;
                if (!is_int($qty) || $qty < 0 || $qty > 1000000000) {
                    throw new UnprocessableEntityHttpException('Count must be a nonnegative integer.');
                }
                $line = $d->items[$index];
                $p = $this->inventory->getProduct($line['product_variant_id'] ?? $line['product_id']);
                $serials = $data['serials'] ?? [];
                if ('serial' === ($p->details['tracking'] ?? $p->parent?->details['tracking'] ?? 'none')) {
                    if (!array_is_list($serials) || count($serials) !== $qty || count(array_unique($serials, SORT_REGULAR)) !== $qty || array_filter($serials, static fn ($v) => !is_string($v) || '' === trim($v) || strlen($v) > 100)) {
                        throw new UnprocessableEntityHttpException('Provide one unique serial per counted unit.');
                    }
                    $d->items[$index]['serials'] = $serials;
                } elseif ($serials) {
                    throw new UnprocessableEntityHttpException('This count line does not use serial tracking.');
                }
                $d->items[$index]['quantity'] = $qty;
                $d->details['count_history'][(string) $lineId][] = ['quantity' => $qty, 'serials' => $serials, 'notes' => $data['notes'] ?? '', 'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM)];
                unset($d->details['count_reviews'][(string) $lineId]);
            }

            return $this->inventory->view($d);
        });
    }

    public function schedules(): array
    {
        return array_map($this->view(...), $this->em->getRepository(CycleCountSchedule::class)->findBy([], ['id' => 'ASC']));
    }

    public function view(CycleCountSchedule $s): array
    {
        return ['id' => $s->id, 'name' => $s->name, 'is_active' => $s->isActive, 'next_run_at' => $s->nextRunAt->format(DATE_ATOM), 'last_run_at' => $s->lastRunAt?->format(DATE_ATOM), 'details' => $s->details];
    }

    public function save(array $data, ?int $id): array
    {
        if (array_diff(array_keys($data), ['name', 'frequency', 'scope_type', 'scope_id', 'products_per_run', 'is_active', 'next_run_at'])) {
            throw new UnprocessableEntityHttpException('Unexpected schedule fields.');
        }

        return $this->transaction(function () use ($data, $id) {
            $s = null === $id ? new CycleCountSchedule() : $this->schedule($id);
            if ($s->id) {
                $this->em->refresh($s, LockMode::PESSIMISTIC_WRITE);
            }
            $values = $data + $s->details;
            if (!trim($data['name'] ?? $s->name) || !in_array($values['frequency'] ?? '', ['daily', 'weekly', 'monthly'], true) || !in_array($values['scope_type'] ?? '', ['all', 'warehouse', 'location', 'category'], true)) {
                throw new UnprocessableEntityHttpException('Provide a name, frequency and scope.');
            }
            $scope = $values['scope_type'];
            if ('all' !== $scope) {
                $this->inventory->get(match ($scope) {
                    'warehouse' => 'warehouses', 'location' => 'locations', default => 'categories',
                }, $values['scope_id'] ?? 0);
            }
            $count = $values['products_per_run'] ?? 50;
            if (!is_int($count) || $count < 1 || $count > 500) {
                throw new UnprocessableEntityHttpException('Count between 1 and 500 items per run.');
            }
            $s->name = $data['name'] ?? $s->name;
            $s->isActive = $data['is_active'] ?? $s->isActive;
            $s->details = array_replace($s->details, ['frequency' => $values['frequency'], 'scope_type' => $scope, 'scope_id' => 'all' === $scope ? null : $values['scope_id'], 'products_per_run' => $count]);
            if (isset($data['next_run_at'])) {
                try {
                    $s->nextRunAt = new \DateTimeImmutable($data['next_run_at']);
                } catch (\Exception) {
                    throw new UnprocessableEntityHttpException('Invalid next-run timestamp.');
                }
            }
            $this->em->persist($s);
            $this->em->flush();

            return $this->view($s);
        });
    }

    public function delete(int $id): void
    {
        $this->transaction(function () use ($id) { $this->em->remove($this->schedule($id)); });
    }

    private function schedule(int $id): CycleCountSchedule
    {
        return $this->em->find(CycleCountSchedule::class, $id) ?? throw new NotFoundHttpException('Schedule not found.');
    }

    public function run(int $id, bool $scheduled = false): array
    {
        return $this->transaction(function () use ($id, $scheduled) {
            $s = $this->schedule($id);
            $this->em->refresh($s, LockMode::PESSIMISTIC_WRITE);
            $now = new \DateTimeImmutable();
            if (!$s->isActive || ($scheduled && $s->nextRunAt > $now)) {
                return ['created' => [], 'message' => 'Schedule is inactive or not yet due.'];
            }
            foreach ($s->details['last_audits'] ?? [] as $previous) {
                $audit = $this->em->getRepository(Document::class)->createQueryBuilder('d')->where('d.id = :id')->setParameter('id', $previous['id'])->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
                if ($audit && 'draft' === $audit->status) {
                    if ($scheduled) {
                        $s->nextRunAt = $now->modify(match ($s->details['frequency']) {
                            'daily' => '+1 day', 'weekly' => '+1 week', default => '+1 month',
                        });
                    }

                    return ['created' => [], 'message' => 'Complete or cancel the previous draft counts first.'];
                }
            }
            $last = [];
            foreach ($this->em->getRepository(Document::class)->findBy(['kind' => 'stock-audits', 'status' => 'completed'], ['id' => 'ASC']) as $audit) {
                foreach ($audit->items as $line) {
                    $last[($line['product_variant_id'] ?? $line['product_id']).':'.$audit->details['location_id']] = $audit->id;
                }
            }
            $balances = $this->em->getRepository(Balance::class)->findAll();
            $scope = $s->details['scope_type'];
            $scopeId = $s->details['scope_id'];
            $balances = array_filter($balances, static fn ($b) => $b->product->isActive && (!$b->product->parent || $b->product->parent->isActive) && match ($scope) {
                'location' => $b->location->id === $scopeId, 'warehouse' => $b->location->warehouse?->id === $scopeId, 'category' => ($b->product->category?->id ?? $b->product->parent?->category?->id) === $scopeId, default => true,
            });
            usort($balances, static fn ($a, $b) => ($last[$a->product->id.':'.$a->location->id] ?? 0) <=> ($last[$b->product->id.':'.$b->location->id] ?? 0) ?: $a->id <=> $b->id);
            $groups = [];
            $remaining = $s->details['products_per_run'];
            foreach ($balances as $b) {
                if ($remaining <= 0) {
                    break;
                }
                $p = $b->product;
                $mode = $p->details['tracking'] ?? $p->parent?->details['tracking'] ?? 'none';
                $traces = 'none' === $mode ? [] : $this->em->getRepository(\App\Entity\Inventory\TraceBalance::class)->findBy(['product' => $p, 'location' => $b->location]);
                $batches = 'batch' === $mode ? array_unique(array_map(static fn ($t) => $t->batch, $traces)) : [''];
                foreach ($batches as $batch) {
                    if ($remaining-- <= 0) {
                        break;
                    }
                    $line = ['product_id' => $p->parent?->id ?? $p->id, 'quantity' => 0];
                    if ($p->parent) {
                        $line['product_variant_id'] = $p->id;
                    }
                    if ('batch' === $mode) {
                        $line['batch'] = $batch;
                    }
                    if ('serial' === $mode) {
                        $line['serials'] = [];
                    }
                    $groups[$b->location->id][] = $line;
                }
            }
            $created = [];
            foreach ($groups as $location => $lines) {
                $d = $this->inventory->create('stock-audits', ['location_id' => $location, 'items' => $lines, 'notes' => 'Cycle count: '.$s->name]);
                $d->details['count_required'] = true;
                $d->details['cycle_schedule_id'] = $s->id;
                $created[] = ['id' => $d->id, 'number' => $d->number];
            }
            $s->lastRunAt = $now;
            if ($scheduled) {
                $s->nextRunAt = $now->modify(match ($s->details['frequency']) {
                    'daily' => '+1 day', 'weekly' => '+1 week', default => '+1 month',
                });
            }
            $s->details['last_audits'] = $created;

            return ['created' => $created];
        });
    }
}
