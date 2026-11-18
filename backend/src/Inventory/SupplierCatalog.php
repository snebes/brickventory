<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\ProductSupplier;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class SupplierCatalog
{
    public function __construct(private EntityManagerInterface $em, private InventoryService $inventory, private DocumentTotals $totals, #[Autowire(env: 'default:dashboard_default_currency:INVENTORY_CURRENCY')] private string $currency)
    {
    }

    public function listing(string $side, int $id, int $page, int $limit): array
    {
        $record = 'product' === $side ? $this->inventory->getProduct($id) : $this->inventory->get('suppliers', $id);
        $qb = $this->em->getRepository(ProductSupplier::class)->createQueryBuilder('link')->addSelect('p', 's')->join('link.product', 'p')->join('link.supplier', 's')->where('link.'.$side.' = :record')->setParameter('record', $record)->orderBy('link.isPrimary', 'DESC')->addOrderBy('link.id', 'ASC');
        $total = $this->em->getRepository(ProductSupplier::class)->count([$side => $record]);

        return ['data' => array_map($this->view(...), $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult()), 'meta' => ['total' => $total, 'current_page' => $page, 'per_page' => $limit]];
    }

    public function save(int $productId, ?int $id, array $data): array
    {
        return $this->em->wrapInTransaction(function () use ($productId, $id, $data) {
            $product = $this->inventory->getProduct($productId);
            $this->em->refresh($product, LockMode::PESSIMISTIC_WRITE);
            if ($id) {
                $link = $this->get($productId, $id);
                $this->em->refresh($link, LockMode::PESSIMISTIC_WRITE);
                if (isset($data['supplier_id']) && $data['supplier_id'] !== $link->supplier->id) {
                    throw new UnprocessableEntityHttpException('Supplier cannot change; create a new supplier link.');
                }
            } else {
                if (!isset($data['supplier_id'])) {
                    throw new UnprocessableEntityHttpException('supplier_id is required.');
                }
                $link = new ProductSupplier();
                $link->product = $product;
                $link->supplier = $this->inventory->get('suppliers', $data['supplier_id']);
                $link->costPrice = $product->cost;
                $link->currency = $link->supplier->details['currency'] ?? $this->currency;
                if (!preg_match('/^[A-Z]{3}$/D', $link->currency)) {
                    $link->currency = 'USD';
                }
                $link->isPrimary = 0 === $this->em->getRepository(ProductSupplier::class)->count(['product' => $product]);
                $this->em->persist($link);
            }
            foreach (['supplier_sku' => 'supplierSku', 'currency' => 'currency', 'minimum_order_quantity' => 'minimumOrderQuantity', 'lead_time_days' => 'leadTimeDays', 'is_primary' => 'isPrimary'] as $field => $property) {
                if (array_key_exists($field, $data)) {
                    $link->$property = $data[$field];
                }
            }
            if (!preg_match('/^[A-Z]{3}$/D', $link->currency)) {
                throw new UnprocessableEntityHttpException('Currency must be a three-letter uppercase code.');
            }
            if (isset($data['cost_price'])) {
                $link->costPrice = $this->totals->money($data['cost_price']);
            }
            if ($link->isPrimary) {
                $others = $this->em->getRepository(ProductSupplier::class)->createQueryBuilder('link')->where('link.product = :product')->andWhere('link.id != :id')->setParameter('product', $product)->setParameter('id', $link->id ?? 0)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getResult();
                foreach ($others as $other) {
                    if ($other !== $link) {
                        $other->isPrimary = false;
                    }
                }
                $link->isPrimary = true;
            }
            $this->em->flush();

            return $this->view($link);
        });
    }

    public function delete(int $productId, int $id): void
    {
        $this->em->wrapInTransaction(function () use ($productId, $id) {
            $this->em->refresh($this->inventory->getProduct($productId), LockMode::PESSIMISTIC_WRITE);
            $this->em->remove($this->get($productId, $id));
        });
    }

    private function get(int $productId, int $id): ProductSupplier
    {
        $link = $this->em->find(ProductSupplier::class, $id);
        if (!$link || $link->product->id !== $productId) {
            throw new NotFoundHttpException('Supplier link not found.');
        }

        return $link;
    }

    private function view(ProductSupplier $link): array
    {
        return ['id' => $link->id, 'product_id' => $link->product->id, 'product_name' => $link->product->name, 'product_sku' => $link->product->sku, 'parent_id' => $link->product->parent?->id, 'supplier_id' => $link->supplier->id, 'supplier_name' => $link->supplier->name, 'supplier_active' => $link->supplier->isActive, 'supplier_sku' => $link->supplierSku, 'cost_price' => $link->costPrice, 'currency' => $link->currency, 'minimum_order_quantity' => $link->minimumOrderQuantity, 'lead_time_days' => $link->leadTimeDays, 'is_primary' => $link->isPrimary];
    }
}
