<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InventoryBalance;
use App\Entity\Item;
use App\Entity\Location;
use App\Repository\InventoryBalanceRepository;
use App\Repository\ItemRepository;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing inventory balances at locations
 */
class InventoryBalanceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryBalanceRepository $inventoryBalanceRepository,
        private readonly ItemRepository $itemRepository,
        private readonly LocationRepository $locationRepository
    ) {
    }

    /**
     * Get balance for item at location
     */
    public function getBalance(int $itemId, int $locationId, ?string $binLocation = null): ?InventoryBalance
    {
        $item = $this->itemRepository->find($itemId);
        $location = $this->locationRepository->find($locationId);

        if (!$item || !$location) {
            return null;
        }

        return $this->inventoryBalanceRepository->findBalance($item, $location, $binLocation);
    }

    /**
     * Create or get existing balance
     */
    public function createBalance(int $itemId, int $locationId, ?string $binLocation = null): InventoryBalance
    {
        $item = $this->itemRepository->find($itemId);
        if (!$item) {
            throw new \InvalidArgumentException("Item with ID {$itemId} not found");
        }

        $location = $this->locationRepository->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        $balance = $this->inventoryBalanceRepository->findOrCreateBalance($item, $location, $binLocation);

        if (!isset($balance->id)) {
            $this->entityManager->persist($balance);
            $this->entityManager->flush();
        }

        return $balance;
    }

    /**
     * Update inventory balance atomically
     *
     * @param int $itemId
     * @param int $locationId
     * @param int $quantityDelta Change in quantity (positive or negative)
     * @param string $transactionType Type of transaction
     * @param string|null $binLocation Optional bin location
     * @return InventoryBalance
     */
    public function updateBalance(
        int $itemId,
        int $locationId,
        int $quantityDelta,
        string $transactionType,
        ?string $binLocation = null
    ): InventoryBalance {
        $balance = $this->createBalance($itemId, $locationId, $binLocation);

        // Note: Pessimistic locking removed as it requires an active transaction.
        // For high-concurrency scenarios, wrap the calling code in a transaction.

        switch ($transactionType) {
            case 'receipt':
            case 'adjustment_increase':
            case 'transfer_in':
                $balance->updateQuantityOnHand($quantityDelta);
                $balance->markMovement();
                break;

            case 'fulfillment':
            case 'adjustment_decrease':
            case 'transfer_out':
                $balance->updateQuantityOnHand($quantityDelta);
                $balance->markMovement();
                break;

            case 'commit':
                $balance->updateQuantityCommitted($quantityDelta);
                break;

            case 'uncommit':
                $balance->updateQuantityCommitted(-abs($quantityDelta));
                break;

            case 'order':
                $balance->updateQuantityOnOrder($quantityDelta);
                break;

            case 'transit_out':
                $balance->updateQuantityInTransit($quantityDelta);
                break;

            case 'transit_in':
                $balance->updateQuantityInTransit(-abs($quantityDelta));
                break;

            case 'reserve':
                $balance->updateQuantityReserved($quantityDelta);
                break;

            case 'unreserve':
                $balance->updateQuantityReserved(-abs($quantityDelta));
                break;

            default:
                throw new \InvalidArgumentException("Unknown transaction type: {$transactionType}");
        }

        $this->entityManager->flush();

        return $balance;
    }

    /**
     * Get balances for an item across all locations
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLocationBalances(int $itemId): array
    {
        $item = $this->itemRepository->find($itemId);
        if (!$item) {
            throw new \InvalidArgumentException("Item with ID {$itemId} not found");
        }

        $balances = $this->inventoryBalanceRepository->findByItem($item);

        return array_map(fn($balance) => [
            'locationId' => $balance->location->id,
            'locationCode' => $balance->location->locationCode,
            'locationName' => $balance->location->locationName,
            'binLocation' => $balance->binLocation,
            'quantityOnHand' => $balance->quantityOnHand,
            'quantityAvailable' => $balance->quantityAvailable,
            'quantityCommitted' => $balance->quantityCommitted,
            'quantityOnOrder' => $balance->quantityOnOrder,
            'quantityInTransit' => $balance->quantityInTransit,
            'quantityReserved' => $balance->quantityReserved,
            'averageCost' => $balance->averageCost,
            'totalValue' => $balance->getTotalValue(),
        ], $balances);
    }

    /**
     * Get total available quantity across all locations
     */
    public function getTotalAvailable(int $itemId): int
    {
        $item = $this->itemRepository->find($itemId);
        if (!$item) {
            throw new \InvalidArgumentException("Item with ID {$itemId} not found");
        }

        return $this->inventoryBalanceRepository->getTotalAvailable($item);
    }

    /**
     * Check if quantity is available at location
     */
    public function checkAvailability(int $itemId, int $locationId, int $quantity): bool
    {
        $balance = $this->getBalance($itemId, $locationId);

        if (!$balance) {
            return false;
        }

        return $balance->hasAvailableQuantity($quantity);
    }

    /**
     * Reserve inventory for an order
     */
    public function reserveInventory(
        int $itemId,
        int $locationId,
        int $quantity,
        int $salesOrderId
    ): InventoryBalance {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        if (!$this->checkAvailability($itemId, $locationId, $quantity)) {
            throw new \InvalidArgumentException('Insufficient available inventory at location');
        }

        return $this->updateBalance($itemId, $locationId, $quantity, 'commit');
    }

    /**
     * Release reservation
     */
    public function releaseReservation(
        int $itemId,
        int $locationId,
        int $quantity,
        int $salesOrderId
    ): InventoryBalance {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        return $this->updateBalance($itemId, $locationId, $quantity, 'uncommit');
    }

    /**
     * Get summary of inventory across all locations
     *
     * @return array<string, mixed>
     */
    public function getInventorySummary(): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $result = $qb->select(
            'COUNT(DISTINCT ib.item) as totalItems',
            'COUNT(DISTINCT ib.location) as totalLocations',
            'SUM(ib.quantityOnHand) as totalOnHand',
            'SUM(ib.quantityAvailable) as totalAvailable',
            'SUM(ib.quantityCommitted) as totalCommitted',
            'SUM(ib.quantityOnOrder) as totalOnOrder',
            'SUM(ib.quantityInTransit) as totalInTransit'
        )
            ->from(InventoryBalance::class, 'ib')
            ->getQuery()
            ->getSingleResult();

        return [
            'totalItems' => (int) $result['totalItems'],
            'totalLocations' => (int) $result['totalLocations'],
            'totalOnHand' => (int) $result['totalOnHand'],
            'totalAvailable' => (int) $result['totalAvailable'],
            'totalCommitted' => (int) $result['totalCommitted'],
            'totalOnOrder' => (int) $result['totalOnOrder'],
            'totalInTransit' => (int) $result['totalInTransit'],
        ];
    }
}
