<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * Find all active locations
     *
     * @return Location[]
     */
    public function findActiveLocations(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.active = :active')
            ->setParameter('active', true)
            ->orderBy('l.locationName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find locations by type
     *
     * @param string $type Location type
     * @param bool|null $activeOnly Filter by active status
     * @return Location[]
     */
    public function findByType(string $type, ?bool $activeOnly = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.locationType = :type')
            ->setParameter('type', $type);

        if ($activeOnly !== null) {
            $qb->andWhere('l.active = :active')
               ->setParameter('active', $activeOnly);
        }

        return $qb->orderBy('l.locationName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find locations that can fulfill orders
     *
     * @return Location[]
     */
    public function findFulfillmentLocations(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.active = :active')
            ->andWhere('l.isTransferSource = :isTransferSource')
            ->andWhere('l.makeInventoryAvailable = :makeInventoryAvailable')
            ->setParameter('active', true)
            ->setParameter('isTransferSource', true)
            ->setParameter('makeInventoryAvailable', true)
            ->orderBy('l.locationName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find locations that can receive inventory
     *
     * @return Location[]
     */
    public function findReceivingLocations(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.active = :active')
            ->andWhere('l.isTransferDestination = :isTransferDestination')
            ->setParameter('active', true)
            ->setParameter('isTransferDestination', true)
            ->orderBy('l.locationName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find location by code
     */
    public function findByCode(string $code): ?Location
    {
        return $this->findOneBy(['locationCode' => $code]);
    }
}
