<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CostLayer;
use App\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for querying and managing cost layers (FIFO inventory costing)
 */
class CostLayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CostLayer::class);
    }

    /**
     * Get all cost layers for a specific item with remaining quantity, ordered by receipt date (FIFO)
     * Optionally filter by location for location-specific FIFO
     *
     * @param Item $item
     * @param int|null $locationId Optional location filter for location-specific FIFO
     * @return CostLayer[]
     */
    public function findAvailableByItem(Item $item, ?int $locationId = null): array
    {
        $qb = $this->createQueryBuilder('cl')
            ->where('cl.item = :item')
            ->andWhere('cl.quantityRemaining > 0')
            ->setParameter('item', $item)
            ->orderBy('cl.receiptDate', 'ASC')
            ->addOrderBy('cl.id', 'ASC');  // Secondary sort by ID for consistency

        // Filter by location if provided (for location-specific FIFO)
        if ($locationId !== null) {
            $qb->andWhere('cl.locationId = :locationId')
               ->setParameter('locationId', $locationId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all cost layers for a specific item, ordered by receipt date (FIFO)
     *
     * @return CostLayer[]
     */
    public function findAllByItem(Item $item): array
    {
        return $this->createQueryBuilder('cl')
            ->where('cl.item = :item')
            ->setParameter('item', $item)
            ->orderBy('cl.receiptDate', 'ASC')
            ->addOrderBy('cl.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total inventory valuation for an item using FIFO cost layers
     */
    public function calculateItemValuation(Item $item): float
    {
        $result = $this->createQueryBuilder('cl')
            ->select('SUM(cl.quantityRemaining * cl.unitCost) as total')
            ->where('cl.item = :item')
            ->andWhere('cl.quantityRemaining > 0')
            ->setParameter('item', $item)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (float) ($result ?? 0.0);
    }

    /**
     * Calculate total inventory valuation across all items using FIFO cost layers
     */
    public function calculateTotalInventoryValuation(): float
    {
        $result = $this->createQueryBuilder('cl')
            ->select('SUM(cl.quantityRemaining * cl.unitCost) as total')
            ->where('cl.quantityRemaining > 0')
            ->getQuery()
            ->getSingleScalarResult();
        
        return (float) ($result ?? 0.0);
    }
}
