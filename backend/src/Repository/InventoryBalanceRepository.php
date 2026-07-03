<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InventoryBalance;
use App\Entity\Item;
use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryBalance>
 */
class InventoryBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryBalance::class);
    }

    /**
     * Find balance for specific item at location
     */
    public function findBalance(Item $item, Location $location, ?string $binLocation = null): ?InventoryBalance
    {
        return $this->findOneBy([
            'item' => $item,
            'location' => $location,
            'binLocation' => $binLocation,
        ]);
    }

    /**
     * Find or create balance for item at location
     */
    public function findOrCreateBalance(Item $item, Location $location, ?string $binLocation = null): InventoryBalance
    {
        $balance = $this->findBalance($item, $location, $binLocation);
        
        if (!$balance) {
            $balance = new InventoryBalance();
            $balance->item = $item;
            $balance->location = $location;
            $balance->binLocation = $binLocation;
        }
        
        return $balance;
    }

    /**
     * Find all balances for an item across all locations
     *
     * @return InventoryBalance[]
     */
    public function findByItem(Item $item): array
    {
        return $this->createQueryBuilder('ib')
            ->where('ib.item = :item')
            ->setParameter('item', $item)
            ->orderBy('ib.location', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all balances at a location
     *
     * @return InventoryBalance[]
     */
    public function findByLocation(Location $location): array
    {
        return $this->createQueryBuilder('ib')
            ->where('ib.location = :location')
            ->andWhere('ib.quantityOnHand > 0')
            ->setParameter('location', $location)
            ->orderBy('ib.item', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total available quantity for an item across all locations
     */
    public function getTotalAvailable(Item $item): int
    {
        $result = $this->createQueryBuilder('ib')
            ->select('SUM(ib.quantityAvailable) as total')
            ->where('ib.item = :item')
            ->setParameter('item', $item)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get total on hand quantity for an item across all locations
     */
    public function getTotalOnHand(Item $item): int
    {
        $result = $this->createQueryBuilder('ib')
            ->select('SUM(ib.quantityOnHand) as total')
            ->where('ib.item = :item')
            ->setParameter('item', $item)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get total on order quantity for an item across all locations
     */
    public function getTotalOnOrder(Item $item): int
    {
        $result = $this->createQueryBuilder('ib')
            ->select('SUM(ib.quantityOnOrder) as total')
            ->where('ib.item = :item')
            ->setParameter('item', $item)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get items with low stock at a location
     *
     * @param Location $location
     * @param int $threshold Minimum quantity threshold
     * @return InventoryBalance[]
     */
    public function findLowStockAtLocation(Location $location, int $threshold = 10): array
    {
        return $this->createQueryBuilder('ib')
            ->where('ib.location = :location')
            ->andWhere('ib.quantityOnHand <= :threshold')
            ->andWhere('ib.quantityOnHand > 0')
            ->setParameter('location', $location)
            ->setParameter('threshold', $threshold)
            ->orderBy('ib.quantityOnHand', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
