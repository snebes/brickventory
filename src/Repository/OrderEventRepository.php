<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for querying order events (event store for Purchase Orders and Sales Orders)
 */
class OrderEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderEvent::class);
    }

    /**
     * Get all events for a specific order, ordered by event date
     *
     * @return OrderEvent[]
     */
    public function findByOrder(string $orderType, int $orderId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.orderType = :orderType')
            ->andWhere('e.orderId = :orderId')
            ->setParameter('orderType', $orderType)
            ->setParameter('orderId', $orderId)
            ->orderBy('e.eventDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all events for purchase orders
     *
     * @return OrderEvent[]
     */
    public function findPurchaseOrderEvents(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.orderType = :orderType')
            ->setParameter('orderType', 'purchase_order')
            ->orderBy('e.eventDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all events for sales orders
     *
     * @return OrderEvent[]
     */
    public function findSalesOrderEvents(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.orderType = :orderType')
            ->setParameter('orderType', 'sales_order')
            ->orderBy('e.eventDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the event history for a specific order
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrderHistory(string $orderType, int $orderId): array
    {
        $events = $this->findByOrder($orderType, $orderId);
        
        return array_map(function (OrderEvent $event) {
            return [
                'id' => $event->id,
                'uuid' => $event->uuid,
                'eventType' => $event->eventType,
                'eventDate' => $event->eventDate->format('Y-m-d H:i:s'),
                'previousState' => $event->previousState ? json_decode($event->previousState, true) : null,
                'newState' => $event->newState ? json_decode($event->newState, true) : null,
                'metadata' => $event->metadata ? json_decode($event->metadata, true) : null,
            ];
        }, $events);
    }
}
