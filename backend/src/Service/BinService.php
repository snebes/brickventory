<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Bin;
use App\Entity\BinInventory;
use App\Entity\Item;
use App\Entity\Location;
use App\Repository\BinRepository;
use App\Repository\BinInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing bins and bin inventory
 */
class BinService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BinRepository $binRepository,
        private readonly BinInventoryRepository $binInventoryRepository
    ) {
    }

    /**
     * Create a new bin at a location
     *
     * @param array<string, mixed> $data
     * @return Bin
     */
    public function createBin(array $data): Bin
    {
        $locationId = $data['locationId'] ?? null;
        if (!$locationId) {
            throw new \InvalidArgumentException('Location ID is required');
        }

        $location = $this->entityManager->getRepository(Location::class)->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        $binCode = $data['binCode'] ?? '';
        if (empty($binCode)) {
            throw new \InvalidArgumentException('Bin code is required');
        }

        // Check if bin code already exists at this location
        $existing = $this->binRepository->findByLocationAndCode($location, $binCode);
        if ($existing) {
            throw new \InvalidArgumentException("Bin code {$binCode} already exists at this location");
        }

        $bin = new Bin();
        $bin->location = $location;
        $bin->binCode = $binCode;
        $bin->binName = $data['binName'] ?? null;
        $bin->binType = $data['binType'] ?? Bin::TYPE_STORAGE;
        $bin->zone = $data['zone'] ?? null;
        $bin->aisle = $data['aisle'] ?? null;
        $bin->row = $data['row'] ?? null;
        $bin->shelf = $data['shelf'] ?? null;
        $bin->level = $data['level'] ?? null;
        $bin->capacity = isset($data['capacity']) ? (float) $data['capacity'] : null;
        $bin->allowMixedItems = $data['allowMixedItems'] ?? true;
        $bin->allowMixedLots = $data['allowMixedLots'] ?? true;
        $bin->notes = $data['notes'] ?? null;

        $this->entityManager->persist($bin);
        $this->entityManager->flush();

        return $bin;
    }

    /**
     * Update bin details
     *
     * @param int $binId
     * @param array<string, mixed> $data
     * @return Bin
     */
    public function updateBin(int $binId, array $data): Bin
    {
        $bin = $this->binRepository->find($binId);
        if (!$bin) {
            throw new \InvalidArgumentException("Bin with ID {$binId} not found");
        }

        if (isset($data['binName'])) {
            $bin->binName = $data['binName'];
        }
        if (isset($data['binType'])) {
            $bin->binType = $data['binType'];
        }
        if (isset($data['zone'])) {
            $bin->zone = $data['zone'];
        }
        if (isset($data['aisle'])) {
            $bin->aisle = $data['aisle'];
        }
        if (isset($data['row'])) {
            $bin->row = $data['row'];
        }
        if (isset($data['shelf'])) {
            $bin->shelf = $data['shelf'];
        }
        if (isset($data['level'])) {
            $bin->level = $data['level'];
        }
        if (isset($data['capacity'])) {
            $bin->capacity = (float) $data['capacity'];
        }
        if (isset($data['allowMixedItems'])) {
            $bin->allowMixedItems = (bool) $data['allowMixedItems'];
        }
        if (isset($data['allowMixedLots'])) {
            $bin->allowMixedLots = (bool) $data['allowMixedLots'];
        }
        if (isset($data['notes'])) {
            $bin->notes = $data['notes'];
        }

        $bin->touch();
        $this->entityManager->flush();

        return $bin;
    }

    /**
     * Deactivate a bin (must be empty)
     *
     * @param int $binId
     * @return Bin
     */
    public function deactivateBin(int $binId): Bin
    {
        $bin = $this->binRepository->find($binId);
        if (!$bin) {
            throw new \InvalidArgumentException("Bin with ID {$binId} not found");
        }

        if (!$bin->isEmpty()) {
            throw new \InvalidArgumentException('Cannot deactivate bin with inventory. Please move inventory first.');
        }

        $bin->active = false;
        $bin->touch();
        $this->entityManager->flush();

        return $bin;
    }

    /**
     * Get inventory in a specific bin
     *
     * @param int $binId
     * @return array<int, array<string, mixed>>
     */
    public function getBinInventory(int $binId): array
    {
        $bin = $this->binRepository->find($binId);
        if (!$bin) {
            throw new \InvalidArgumentException("Bin with ID {$binId} not found");
        }

        $inventories = $this->binInventoryRepository->findByBin($bin);

        return array_map(function (BinInventory $inv) {
            return [
                'id' => $inv->id,
                'itemId' => $inv->item->id,
                'itemName' => $inv->item->itemName,
                'quantity' => $inv->quantity,
                'lotNumber' => $inv->lotNumber,
                'qualityStatus' => $inv->qualityStatus,
                'expirationDate' => $inv->expirationDate?->format('Y-m-d'),
                'lastMovementDate' => $inv->lastMovementDate?->format('c'),
            ];
        }, $inventories);
    }

    /**
     * Suggest bin for put-away or picking
     *
     * @param int $locationId
     * @param int $itemId
     * @param int $quantity
     * @param string $operation 'putaway' or 'pick'
     * @return Bin|null
     */
    public function suggestBin(int $locationId, int $itemId, int $quantity, string $operation = 'putaway'): ?Bin
    {
        $location = $this->entityManager->getRepository(Location::class)->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        $item = $this->entityManager->getRepository(Item::class)->find($itemId);
        if (!$item) {
            throw new \InvalidArgumentException("Item with ID {$itemId} not found");
        }

        if ($operation === 'putaway') {
            return $this->suggestPutawayBin($location, $item, $quantity);
        } elseif ($operation === 'pick') {
            return $this->suggestPickBin($location, $item, $quantity);
        }

        throw new \InvalidArgumentException("Invalid operation: {$operation}");
    }

    /**
     * Suggest bin for putting away inventory
     */
    private function suggestPutawayBin(Location $location, Item $item, int $quantity): ?Bin
    {
        // First, try to find bins that already have this item (consolidation)
        $existingInventories = $this->binInventoryRepository->findAvailableByItemAndLocation($item, $location);
        
        foreach ($existingInventories as $inv) {
            if ($inv->bin->canAcceptInventory($quantity) && $inv->bin->allowMixedLots) {
                return $inv->bin;
            }
        }

        // If not found, look for empty storage bins
        $storageBins = $this->binRepository->findByLocationAndType($location, Bin::TYPE_STORAGE);
        foreach ($storageBins as $bin) {
            if ($bin->isEmpty() && $bin->canAcceptInventory($quantity)) {
                return $bin;
            }
        }

        // Finally, try any available bin with capacity
        $availableBins = $this->binRepository->findWithAvailableCapacity($location, $quantity);
        return $availableBins[0] ?? null;
    }

    /**
     * Suggest bin for picking inventory
     */
    private function suggestPickBin(Location $location, Item $item, int $quantity): ?Bin
    {
        // First, check picking bins
        $pickingBins = $this->binRepository->findByLocationAndType($location, Bin::TYPE_PICKING);
        foreach ($pickingBins as $bin) {
            $inventories = $this->binInventoryRepository->findByBin($bin);
            foreach ($inventories as $inv) {
                if ($inv->item->id === $item->id && $inv->isAvailable() && $inv->quantity >= $quantity) {
                    return $bin;
                }
            }
        }

        // Then check storage bins
        $inventories = $this->binInventoryRepository->findAvailableByItemAndLocation($item, $location);
        foreach ($inventories as $inv) {
            if ($inv->quantity >= $quantity) {
                return $inv->bin;
            }
        }

        return null;
    }

    /**
     * Validate bin for operation
     *
     * @param int $binId
     * @param int $itemId
     * @param int $quantity
     * @param string $operation
     * @return bool
     */
    public function validateBinForOperation(int $binId, int $itemId, int $quantity, string $operation): bool
    {
        $bin = $this->binRepository->find($binId);
        if (!$bin || !$bin->active) {
            return false;
        }

        $item = $this->entityManager->getRepository(Item::class)->find($itemId);
        if (!$item) {
            return false;
        }

        if ($operation === 'putaway') {
            return $bin->canAcceptInventory($quantity);
        } elseif ($operation === 'pick') {
            $inventories = $this->binInventoryRepository->findByBin($bin);
            foreach ($inventories as $inv) {
                if ($inv->item->id === $itemId && $inv->isAvailable() && $inv->quantity >= $quantity) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Transfer inventory between bins
     *
     * @param int $fromBinId
     * @param int $toBinId
     * @param int $itemId
     * @param int $quantity
     * @param string|null $lotNumber
     * @return void
     */
    public function transferBetweenBins(
        int $fromBinId,
        int $toBinId,
        int $itemId,
        int $quantity,
        ?string $lotNumber = null
    ): void {
        $fromBin = $this->binRepository->find($fromBinId);
        $toBin = $this->binRepository->find($toBinId);
        
        if (!$fromBin || !$toBin) {
            throw new \InvalidArgumentException('Invalid bin IDs');
        }

        if ($fromBin->location->id !== $toBin->location->id) {
            throw new \InvalidArgumentException('Bins must be at the same location');
        }

        $item = $this->entityManager->getRepository(Item::class)->find($itemId);
        if (!$item) {
            throw new \InvalidArgumentException("Item with ID {$itemId} not found");
        }

        // Find source inventory
        $fromInventory = $this->binInventoryRepository->findOrCreate(
            $item,
            $fromBin->location,
            $fromBin,
            $lotNumber
        );

        if ($fromInventory->quantity < $quantity) {
            throw new \InvalidArgumentException('Insufficient quantity in source bin');
        }

        // Check destination capacity
        if (!$toBin->canAcceptInventory($quantity)) {
            throw new \InvalidArgumentException('Destination bin does not have sufficient capacity');
        }

        // Find or create destination inventory
        $toInventory = $this->binInventoryRepository->findOrCreate(
            $item,
            $toBin->location,
            $toBin,
            $lotNumber
        );

        // Perform transfer
        $fromInventory->removeQuantity($quantity);
        $toInventory->addQuantity($quantity);

        // Update bin utilization
        $fromBin->currentUtilization -= $quantity;
        $toBin->currentUtilization += $quantity;

        // Persist if new
        if (!$this->entityManager->contains($fromInventory)) {
            $this->entityManager->persist($fromInventory);
        }
        if (!$this->entityManager->contains($toInventory)) {
            $this->entityManager->persist($toInventory);
        }

        $this->entityManager->flush();
    }
}
