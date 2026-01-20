<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Bin;
use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Bin entity
 */
class BinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bin::class);
    }

    /**
     * Find active bins at a location
     *
     * @param Location $location
     * @return Bin[]
     */
    public function findActiveByLocation(Location $location): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.location = :location')
            ->andWhere('b.active = true')
            ->setParameter('location', $location)
            ->orderBy('b.binCode', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bins by location and type
     *
     * @param Location $location
     * @param string $type
     * @return Bin[]
     */
    public function findByLocationAndType(Location $location, string $type): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.location = :location')
            ->andWhere('b.binType = :type')
            ->andWhere('b.active = true')
            ->setParameter('location', $location)
            ->setParameter('type', $type)
            ->orderBy('b.binCode', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bin by location and bin code
     *
     * @param Location $location
     * @param string $binCode
     * @return Bin|null
     */
    public function findByLocationAndCode(Location $location, string $binCode): ?Bin
    {
        return $this->createQueryBuilder('b')
            ->where('b.location = :location')
            ->andWhere('b.binCode = :binCode')
            ->setParameter('location', $location)
            ->setParameter('binCode', $binCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find bins with available capacity at a location
     *
     * @param Location $location
     * @param float $requiredCapacity
     * @return Bin[]
     */
    public function findWithAvailableCapacity(Location $location, float $requiredCapacity = 0): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.location = :location')
            ->andWhere('b.active = true')
            ->setParameter('location', $location);

        if ($requiredCapacity > 0) {
            $qb->andWhere('(b.capacity IS NULL OR (b.capacity - b.currentUtilization) >= :requiredCapacity)')
               ->setParameter('requiredCapacity', $requiredCapacity);
        }

        return $qb->orderBy('b.binCode', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find empty bins at a location
     *
     * @param Location $location
     * @return Bin[]
     */
    public function findEmptyBins(Location $location): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.location = :location')
            ->andWhere('b.active = true')
            ->andWhere('b.currentUtilization = 0')
            ->setParameter('location', $location)
            ->orderBy('b.binCode', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bins by zone
     *
     * @param Location $location
     * @param string $zone
     * @return Bin[]
     */
    public function findByZone(Location $location, string $zone): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.location = :location')
            ->andWhere('b.zone = :zone')
            ->andWhere('b.active = true')
            ->setParameter('location', $location)
            ->setParameter('zone', $zone)
            ->orderBy('b.binCode', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
