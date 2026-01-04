<?php

declare(strict_types=1);

namespace App\MessageHandler\Query;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Message\Query\GetSalesOrderQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetSalesOrderQueryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetSalesOrderQuery $query): ?array
    {
        $so = $this->entityManager->getRepository(SalesOrder::class)->find($query->id);
        
        if (!$so) {
            return null;
        }

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
    }
}
