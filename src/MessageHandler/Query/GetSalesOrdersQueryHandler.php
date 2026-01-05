<?php

declare(strict_types=1);

namespace App\MessageHandler\Query;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Message\Query\GetSalesOrdersQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetSalesOrdersQueryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(GetSalesOrdersQuery $query): array
    {
        $qb = $this->entityManager
            ->getRepository(SalesOrder::class)
            ->createQueryBuilder('so')
            ->orderBy('so.orderDate', 'DESC');

        if ($query->status) {
            $qb->andWhere('so.status = :status')
               ->setParameter('status', $query->status);
        }

        if ($query->orderDateFrom) {
            $qb->andWhere('so.orderDate >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($query->orderDateFrom));
        }

        if ($query->orderDateTo) {
            $qb->andWhere('so.orderDate <= :dateTo')
               ->setParameter('dateTo', new \DateTime($query->orderDateTo));
        }

        $qb->setFirstResult(($query->page - 1) * $query->perPage)
           ->setMaxResults($query->perPage);

        $salesOrders = $qb->getQuery()->getResult();

        return array_map(function (SalesOrder $so) {
            return [
                'id' => $so->id,
                'orderNumber' => $so->orderNumber,
                'orderDate' => $so->orderDate->format('Y-m-d H:i:s'),
                'status' => $so->status,
                'notes' => $so->notes,
                'lines' => array_map(function (SalesOrderLine $line) {
                    return [
                        'id' => $line->id,
                        'item' => [
                            'id' => $line->item->id,
                            'itemId' => $line->item->itemId,
                            'itemName' => $line->item->itemName,
                        ],
                        'quantityOrdered' => $line->quantityOrdered,
                        'quantityFulfilled' => $line->quantityFulfilled,
                    ];
                }, $so->lines->toArray()),
            ];
        }, $salesOrders);
    }
}
