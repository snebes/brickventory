<?php

declare(strict_types=1);

namespace App\MessageHandler\Query;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Message\Query\GetPurchaseOrdersQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetPurchaseOrdersQueryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(GetPurchaseOrdersQuery $query): array
    {
        $qb = $this->entityManager
            ->getRepository(PurchaseOrder::class)
            ->createQueryBuilder('po')
            ->orderBy('po.orderDate', 'DESC');

        if ($query->status) {
            $qb->andWhere('po.status = :status')
               ->setParameter('status', $query->status);
        }

        if ($query->orderDateFrom) {
            $qb->andWhere('po.orderDate >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($query->orderDateFrom));
        }

        if ($query->orderDateTo) {
            $qb->andWhere('po.orderDate <= :dateTo')
               ->setParameter('dateTo', new \DateTime($query->orderDateTo));
        }

        $qb->setFirstResult(($query->page - 1) * $query->perPage)
           ->setMaxResults($query->perPage);

        $purchaseOrders = $qb->getQuery()->getResult();

        return array_map(function (PurchaseOrder $po) {
            return [
                'id' => $po->id,
                'orderNumber' => $po->orderNumber,
                'orderDate' => $po->orderDate->format('Y-m-d H:i:s'),
                'status' => $po->status,
                'reference' => $po->reference,
                'notes' => $po->notes,
                'lines' => array_map(function (PurchaseOrderLine $line) {
                    return [
                        'id' => $line->id,
                        'item' => [
                            'id' => $line->item->id,
                            'itemId' => $line->item->itemId,
                            'itemName' => $line->item->itemName,
                        ],
                        'quantityOrdered' => $line->quantityOrdered,
                        'quantityReceived' => $line->quantityReceived,
                        'rate' => $line->rate,
                    ];
                }, $po->lines->toArray()),
            ];
        }, $purchaseOrders);
    }
}
