<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Location;
use App\Repository\LocationRepository;
use App\Repository\InventoryBalanceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing locations
 */
class LocationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocationRepository $locationRepository,
        private readonly InventoryBalanceRepository $inventoryBalanceRepository
    ) {
    }

    /**
     * Create a new location
     *
     * @param array<string, mixed> $data Location data
     * @return Location
     */
    public function createLocation(array $data): Location
    {
        // Check if location code already exists
        if (isset($data['locationCode'])) {
            $existing = $this->locationRepository->findByCode($data['locationCode']);
            if ($existing) {
                throw new \InvalidArgumentException(
                    sprintf('Location with code "%s" already exists', $data['locationCode'])
                );
            }
        }

        $location = new Location();
        $this->updateLocationFromData($location, $data);

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        return $location;
    }

    /**
     * Update a location
     *
     * @param int $locationId
     * @param array<string, mixed> $data
     * @return Location
     */
    public function updateLocation(int $locationId, array $data): Location
    {
        $location = $this->locationRepository->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        // Check if location code is being changed and if it conflicts
        if (isset($data['locationCode']) && $data['locationCode'] !== $location->locationCode) {
            $existing = $this->locationRepository->findByCode($data['locationCode']);
            if ($existing && $existing->id !== $locationId) {
                throw new \InvalidArgumentException(
                    sprintf('Location with code "%s" already exists', $data['locationCode'])
                );
            }
        }

        $this->updateLocationFromData($location, $data);
        $location->touch();

        $this->entityManager->flush();

        return $location;
    }

    /**
     * Activate a location
     */
    public function activateLocation(int $locationId): Location
    {
        $location = $this->locationRepository->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        $location->active = true;
        $location->touch();
        $this->entityManager->flush();

        return $location;
    }

    /**
     * Deactivate a location (must have zero inventory)
     */
    public function deactivateLocation(int $locationId): Location
    {
        $location = $this->locationRepository->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        // Check if location has inventory
        $balances = $this->inventoryBalanceRepository->findByLocation($location);
        if (count($balances) > 0) {
            throw new \InvalidArgumentException(
                'Cannot deactivate location with inventory on hand. Transfer or adjust inventory first.'
            );
        }

        $location->active = false;
        $location->touch();
        $this->entityManager->flush();

        return $location;
    }

    /**
     * Get inventory at location for an item
     *
     * @param int $locationId
     * @param int|null $itemId Optional item ID filter
     * @return array<int, mixed>
     */
    public function getLocationInventory(int $locationId, ?int $itemId = null): array
    {
        $location = $this->locationRepository->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        $balances = $this->inventoryBalanceRepository->findByLocation($location);

        if ($itemId !== null) {
            $balances = array_filter($balances, fn($balance) => $balance->item->id === $itemId);
        }

        return array_map(fn($balance) => [
            'itemId' => $balance->item->id,
            'itemName' => $balance->item->itemName,
            'binLocation' => $balance->binLocation,
            'quantityOnHand' => $balance->quantityOnHand,
            'quantityAvailable' => $balance->quantityAvailable,
            'quantityCommitted' => $balance->quantityCommitted,
            'quantityOnOrder' => $balance->quantityOnOrder,
            'quantityInTransit' => $balance->quantityInTransit,
            'averageCost' => $balance->averageCost,
            'totalValue' => $balance->getTotalValue(),
        ], $balances);
    }

    /**
     * Validate location for a transaction type
     */
    public function validateLocationForTransaction(int $locationId, string $transactionType): void
    {
        $location = $this->locationRepository->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        if (!$location->active) {
            throw new \InvalidArgumentException("Location {$location->locationCode} is not active");
        }

        switch ($transactionType) {
            case 'receipt':
            case 'transfer_in':
                if (!$location->isTransferDestination) {
                    throw new \InvalidArgumentException(
                        "Location {$location->locationCode} cannot receive inventory"
                    );
                }
                break;

            case 'fulfillment':
            case 'transfer_out':
                if (!$location->isTransferSource) {
                    throw new \InvalidArgumentException(
                        "Location {$location->locationCode} cannot ship inventory"
                    );
                }
                if (!$location->makeInventoryAvailable && $transactionType === 'fulfillment') {
                    throw new \InvalidArgumentException(
                        "Location {$location->locationCode} inventory is not available for fulfillment"
                    );
                }
                break;

            case 'adjustment':
                // Adjustments allowed at any active location
                break;

            default:
                throw new \InvalidArgumentException("Unknown transaction type: {$transactionType}");
        }
    }

    /**
     * Get available locations for an item (locations with available inventory)
     *
     * @param int $itemId
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableLocationsForItem(int $itemId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $results = $qb->select('l', 'ib')
            ->from(Location::class, 'l')
            ->innerJoin('ib.location', 'l')
            ->from(\App\Entity\InventoryBalance::class, 'ib')
            ->where('ib.item = :itemId')
            ->andWhere('ib.quantityAvailable > 0')
            ->andWhere('l.active = :active')
            ->andWhere('l.makeInventoryAvailable = :makeAvailable')
            ->setParameter('itemId', $itemId)
            ->setParameter('active', true)
            ->setParameter('makeAvailable', true)
            ->getQuery()
            ->getResult();

        return array_map(fn($result) => [
            'locationId' => $result['l']->id,
            'locationCode' => $result['l']->locationCode,
            'locationName' => $result['l']->locationName,
            'quantityAvailable' => $result['ib']->quantityAvailable,
        ], $results);
    }

    /**
     * Update location fields from data array
     *
     * @param Location $location
     * @param array<string, mixed> $data
     */
    private function updateLocationFromData(Location $location, array $data): void
    {
        if (isset($data['locationCode'])) {
            $location->locationCode = $data['locationCode'];
        }
        if (isset($data['locationName'])) {
            $location->locationName = $data['locationName'];
        }
        if (isset($data['locationType'])) {
            $location->locationType = $data['locationType'];
        }
        if (isset($data['active'])) {
            $location->active = (bool) $data['active'];
        }
        if (isset($data['address'])) {
            $location->address = $data['address'];
        }
        if (isset($data['timeZone'])) {
            $location->timeZone = $data['timeZone'];
        }
        if (isset($data['country'])) {
            $location->country = $data['country'];
        }
        if (isset($data['useBinManagement'])) {
            $location->useBinManagement = (bool) $data['useBinManagement'];
        }
        if (isset($data['requiresBinOnReceipt'])) {
            $location->requiresBinOnReceipt = (bool) $data['requiresBinOnReceipt'];
        }
        if (isset($data['requiresBinOnFulfillment'])) {
            $location->requiresBinOnFulfillment = (bool) $data['requiresBinOnFulfillment'];
        }
        if (isset($data['defaultBinLocation'])) {
            $location->defaultBinLocation = $data['defaultBinLocation'];
        }
        if (isset($data['allowNegativeInventory'])) {
            $location->allowNegativeInventory = (bool) $data['allowNegativeInventory'];
        }
        if (isset($data['isTransferSource'])) {
            $location->isTransferSource = (bool) $data['isTransferSource'];
        }
        if (isset($data['isTransferDestination'])) {
            $location->isTransferDestination = (bool) $data['isTransferDestination'];
        }
        if (isset($data['makeInventoryAvailable'])) {
            $location->makeInventoryAvailable = (bool) $data['makeInventoryAvailable'];
        }
        if (isset($data['managerId'])) {
            $location->managerId = $data['managerId'];
        }
        if (isset($data['contactPhone'])) {
            $location->contactPhone = $data['contactPhone'];
        }
        if (isset($data['contactEmail'])) {
            $location->contactEmail = $data['contactEmail'];
        }
    }
}
