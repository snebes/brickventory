<?php

declare(strict_types=1);

namespace App\MessageHandler\Query;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Message\Query\GetPurchaseOrderQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetPurchaseOrderQueryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetPurchaseOrderQuery $query): ?array
    {
        $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($query->id);
        
        if (!$po) {
            return null;
        }

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
    }
}
