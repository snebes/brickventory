<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Bin;
use App\Entity\BinInventory;
use App\Entity\Item;
use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for BinInventory entity
 */
class BinInventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BinInventory::class);
    }

    /**
     * Find inventory in a specific bin
     *
     * @param Bin $bin
     * @return BinInventory[]
     */
    public function findByBin(Bin $bin): array
    {
        return $this->createQueryBuilder('bi')
            ->where('bi.bin = :bin')
            ->setParameter('bin', $bin)
            ->orderBy('bi.item', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find inventory for an item across all bins at a location
     *
     * @param Item $item
     * @param Location $location
     * @return BinInventory[]
     */
    public function findByItemAndLocation(Item $item, Location $location): array
    {
        return $this->createQueryBuilder('bi')
            ->where('bi.item = :item')
            ->andWhere('bi.location = :location')
            ->setParameter('item', $item)
            ->setParameter('location', $location)
            ->orderBy('bi.bin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find available inventory for an item at a location
     *
     * @param Item $item
     * @param Location $location
     * @return BinInventory[]
     */
    public function findAvailableByItemAndLocation(Item $item, Location $location): array
    {
        return $this->createQueryBuilder('bi')
            ->where('bi.item = :item')
            ->andWhere('bi.location = :location')
            ->andWhere('bi.qualityStatus = :status')
            ->andWhere('bi.quantity > 0')
            ->setParameter('item', $item)
            ->setParameter('location', $location)
            ->setParameter('status', BinInventory::QUALITY_AVAILABLE)
            ->orderBy('bi.bin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find or create bin inventory
     *
     * @param Item $item
     * @param Location $location
     * @param Bin $bin
     * @param string|null $lotNumber
     * @return BinInventory
     */
    public function findOrCreate(
        Item $item,
        Location $location,
        Bin $bin,
        ?string $lotNumber = null
    ): BinInventory {
        $qb = $this->createQueryBuilder('bi')
            ->where('bi.item = :item')
            ->andWhere('bi.location = :location')
            ->andWhere('bi.bin = :bin')
            ->setParameter('item', $item)
            ->setParameter('location', $location)
            ->setParameter('bin', $bin);

        if ($lotNumber !== null) {
            $qb->andWhere('bi.lotNumber = :lotNumber')
               ->setParameter('lotNumber', $lotNumber);
        } else {
            $qb->andWhere('bi.lotNumber IS NULL');
        }

        $existing = $qb->getQuery()->getOneOrNullResult();

        if ($existing) {
            return $existing;
        }

        // Create new bin inventory
        $binInventory = new BinInventory();
        $binInventory->item = $item;
        $binInventory->location = $location;
        $binInventory->bin = $bin;
        $binInventory->lotNumber = $lotNumber;
        $binInventory->quantity = 0;

        return $binInventory;
    }

    /**
     * Get total quantity for an item at a location across all bins
     *
     * @param Item $item
     * @param Location $location
     * @return int
     */
    public function getTotalQuantity(Item $item, Location $location): int
    {
        $result = $this->createQueryBuilder('bi')
            ->select('SUM(bi.quantity)')
            ->where('bi.item = :item')
            ->andWhere('bi.location = :location')
            ->andWhere('bi.qualityStatus = :status')
            ->setParameter('item', $item)
            ->setParameter('location', $location)
            ->setParameter('status', BinInventory::QUALITY_AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Find inventory with expiring items
     *
     * @param Location $location
     * @param int $daysUntilExpiration
     * @return BinInventory[]
     */
    public function findExpiring(Location $location, int $daysUntilExpiration = 30): array
    {
        $expirationDate = new \DateTime("+{$daysUntilExpiration} days");

        return $this->createQueryBuilder('bi')
            ->where('bi.location = :location')
            ->andWhere('bi.expirationDate IS NOT NULL')
            ->andWhere('bi.expirationDate <= :expirationDate')
            ->andWhere('bi.quantity > 0')
            ->setParameter('location', $location)
            ->setParameter('expirationDate', $expirationDate)
            ->orderBy('bi.expirationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
