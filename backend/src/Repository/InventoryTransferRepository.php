<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InventoryTransfer;
use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for InventoryTransfer entity
 */
class InventoryTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryTransfer::class);
    }

    /**
     * Find transfers by status
     *
     * @param string $status
     * @return InventoryTransfer[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.transferDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending transfers
     *
     * @return InventoryTransfer[]
     */
    public function findPending(): array
    {
        return $this->findByStatus(InventoryTransfer::STATUS_PENDING);
    }

    /**
     * Find in-transit transfers
     *
     * @return InventoryTransfer[]
     */
    public function findInTransit(): array
    {
        return $this->findByStatus(InventoryTransfer::STATUS_IN_TRANSIT);
    }

    /**
     * Find transfers from a specific location
     *
     * @param Location $location
     * @return InventoryTransfer[]
     */
    public function findByFromLocation(Location $location): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.fromLocation = :location')
            ->setParameter('location', $location)
            ->orderBy('t.transferDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find transfers to a specific location
     *
     * @param Location $location
     * @return InventoryTransfer[]
     */
    public function findByToLocation(Location $location): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.toLocation = :location')
            ->setParameter('location', $location)
            ->orderBy('t.transferDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find transfers between two locations
     *
     * @param Location $fromLocation
     * @param Location $toLocation
     * @return InventoryTransfer[]
     */
    public function findBetweenLocations(Location $fromLocation, Location $toLocation): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.fromLocation = :fromLocation')
            ->andWhere('t.toLocation = :toLocation')
            ->setParameter('fromLocation', $fromLocation)
            ->setParameter('toLocation', $toLocation)
            ->orderBy('t.transferDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find transfers by date range
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return InventoryTransfer[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.transferDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.transferDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find transfers requiring approval
     *
     * @return InventoryTransfer[]
     */
    public function findRequiringApproval(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.approvedBy IS NULL')
            ->setParameter('status', InventoryTransfer::STATUS_PENDING)
            ->orderBy('t.transferDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
