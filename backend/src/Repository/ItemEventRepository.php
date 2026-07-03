<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Item;
use App\Entity\ItemEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for querying item events (event store)
 */
class ItemEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemEvent::class);
    }

    /**
     * Get all events for a specific item, ordered by event date
     *
     * @return ItemEvent[]
     */
    public function findByItem(Item $item): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.item = :item')
            ->setParameter('item', $item)
            ->orderBy('e.eventDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate item quantities from events (event sourcing reconstruction)
     */
    public function calculateQuantitiesFromEvents(Item $item): array
    {
        $events = $this->findByItem($item);
        
        $quantityOnHand = 0;
        $quantityOnOrder = 0;
        
        foreach ($events as $event) {
            switch ($event->eventType) {
                case 'item_received':
                    $quantityOnHand += $event->quantityChange;
                    break;
                case 'item_fulfilled':
                    $quantityOnHand += $event->quantityChange; // quantityChange is negative
                    break;
                case 'purchase_order_created':
                    $quantityOnOrder += $event->quantityChange;
                    break;
                case 'inventory_adjusted':
                    $quantityOnHand += $event->quantityChange; // can be positive or negative
                    break;
            }
        }
        
        return [
            'quantityOnHand' => $quantityOnHand,
            'quantityOnOrder' => $quantityOnOrder,
        ];
    }
}
