<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InventoryTransfer;
use App\Entity\InventoryTransferLine;
use App\Entity\Item;
use App\Entity\Location;
use App\Repository\InventoryTransferRepository;
use App\Repository\CostLayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for managing inventory transfers between locations
 */
class InventoryTransferService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryTransferRepository $transferRepository,
        private readonly CostLayerRepository $costLayerRepository,
        private readonly InventoryBalanceService $inventoryBalanceService,
        private readonly FIFOLayerService $fifoLayerService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Create a new inventory transfer
     *
     * @param array<string, mixed> $data
     * @return InventoryTransfer
     */
    public function createTransfer(array $data): InventoryTransfer
    {
        $fromLocationId = $data['fromLocationId'] ?? null;
        $toLocationId = $data['toLocationId'] ?? null;

        if (!$fromLocationId || !$toLocationId) {
            throw new \InvalidArgumentException('From and To location IDs are required');
        }

        if ($fromLocationId === $toLocationId) {
            throw new \InvalidArgumentException('Cannot transfer to the same location. Use bin transfer instead.');
        }

        $fromLocation = $this->entityManager->getRepository(Location::class)->find($fromLocationId);
        $toLocation = $this->entityManager->getRepository(Location::class)->find($toLocationId);

        if (!$fromLocation || !$toLocation) {
            throw new \InvalidArgumentException('Invalid location IDs');
        }

        // Validate locations
        $this->validateTransfer($fromLocation, $toLocation, $data['lines'] ?? []);

        $transfer = new InventoryTransfer();
        $transfer->fromLocation = $fromLocation;
        $transfer->toLocation = $toLocation;
        $transfer->transferType = $data['transferType'] ?? InventoryTransfer::TYPE_STANDARD;
        $transfer->requestedBy = $data['requestedBy'] ?? 'system';
        $transfer->notes = $data['notes'] ?? null;
        $transfer->expectedDeliveryDate = isset($data['expectedDeliveryDate']) 
            ? new \DateTime($data['expectedDeliveryDate']) 
            : null;

        $this->entityManager->persist($transfer);

        // Add transfer lines
        foreach ($data['lines'] ?? [] as $lineData) {
            $this->addTransferLine($transfer, $lineData);
        }

        $this->entityManager->flush();

        return $transfer;
    }

    /**
     * Add a line to a transfer
     *
     * @param InventoryTransfer $transfer
     * @param array<string, mixed> $lineData
     * @return InventoryTransferLine
     */
    private function addTransferLine(InventoryTransfer $transfer, array $lineData): InventoryTransferLine
    {
        $itemId = $lineData['itemId'] ?? null;
        $quantity = $lineData['quantity'] ?? 0;

        if (!$itemId || $quantity <= 0) {
            throw new \InvalidArgumentException('Invalid line data');
        }

        $item = $this->entityManager->getRepository(Item::class)->find($itemId);
        if (!$item) {
            throw new \InvalidArgumentException("Item with ID {$itemId} not found");
        }

        // Check availability at source location
        $available = $this->inventoryBalanceService->checkAvailability(
            $itemId,
            $transfer->fromLocation->id,
            $quantity
        );

        if (!$available) {
            throw new \InvalidArgumentException("Insufficient inventory for item {$item->itemName} at source location");
        }

        $line = new InventoryTransferLine();
        $line->inventoryTransfer = $transfer;
        $line->item = $item;
        $line->quantityRequested = $quantity;
        $line->fromBinLocation = $lineData['fromBinLocation'] ?? null;
        $line->toBinLocation = $lineData['toBinLocation'] ?? null;
        $line->lotNumber = $lineData['lotNumber'] ?? null;
        $line->notes = $lineData['notes'] ?? null;

        $transfer->lines->add($line);
        $this->entityManager->persist($line);

        return $line;
    }

    /**
     * Approve a transfer
     *
     * @param int $transferId
     * @param string $approverId
     * @return InventoryTransfer
     */
    public function approveTransfer(int $transferId, string $approverId): InventoryTransfer
    {
        $transfer = $this->transferRepository->find($transferId);
        if (!$transfer) {
            throw new \InvalidArgumentException("Transfer with ID {$transferId} not found");
        }

        if (!$transfer->isPending()) {
            throw new \InvalidArgumentException('Only pending transfers can be approved');
        }

        $transfer->approve($approverId);
        $this->entityManager->flush();

        return $transfer;
    }

    /**
     * Ship a transfer (consume FIFO from source location)
     *
     * @param int $transferId
     * @param string $shippedBy
     * @param array<string, mixed> $data
     * @return InventoryTransfer
     */
    public function shipTransfer(int $transferId, string $shippedBy, array $data = []): InventoryTransfer
    {
        $transfer = $this->transferRepository->find($transferId);
        if (!$transfer) {
            throw new \InvalidArgumentException("Transfer with ID {$transferId} not found");
        }

        if (!$transfer->isPending()) {
            throw new \InvalidArgumentException('Only pending transfers can be shipped');
        }

        $this->entityManager->beginTransaction();

        try {
            foreach ($transfer->lines as $line) {
                $quantityToShip = $line->quantityRequested;

                // Calculate FIFO cost from source location
                $costResult = $this->fifoLayerService->consumeLayersFIFO(
                    $line->item,
                    $transfer->fromLocation->id,
                    $quantityToShip,
                    'transfer_out',
                    $transfer->id
                );

                $averageUnitCost = $costResult['totalCost'] / $quantityToShip;

                // Record shipped quantity
                $line->recordShipped($quantityToShip, $averageUnitCost);

                // Update inventory balance at source (remove from on-hand, add to in-transit)
                $this->inventoryBalanceService->updateBalance(
                    $line->item->id,
                    $transfer->fromLocation->id,
                    -$quantityToShip,
                    'transit_out',
                    $line->fromBinLocation
                );
            }

            // Set shipping details
            $transfer->markAsShipped($shippedBy);
            $transfer->carrier = $data['carrier'] ?? null;
            $transfer->trackingNumber = $data['trackingNumber'] ?? null;
            $transfer->shippingCost = isset($data['shippingCost']) ? (float) $data['shippingCost'] : null;

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $transfer;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Receive a transfer at destination (create new cost layers at destination)
     *
     * @param int $transferId
     * @param string $receivedBy
     * @param array<int, array<string, mixed>> $receiveData
     * @return InventoryTransfer
     */
    public function receiveTransfer(int $transferId, string $receivedBy, array $receiveData = []): InventoryTransfer
    {
        $transfer = $this->transferRepository->find($transferId);
        if (!$transfer) {
            throw new \InvalidArgumentException("Transfer with ID {$transferId} not found");
        }

        if (!$transfer->isInTransit()) {
            throw new \InvalidArgumentException('Only in-transit transfers can be received');
        }

        $this->entityManager->beginTransaction();

        try {
            foreach ($transfer->lines as $line) {
                $quantityToReceive = $line->quantityShipped;

                // Check if specific quantity provided in receive data
                foreach ($receiveData as $lineReceive) {
                    if ($lineReceive['lineId'] === $line->id) {
                        $quantityToReceive = $lineReceive['quantityReceived'] ?? $line->quantityShipped;
                        break;
                    }
                }

                // Create new cost layer at destination with same unit cost from source
                $costLayer = new \App\Entity\CostLayer();
                $costLayer->item = $line->item;
                $costLayer->locationId = $transfer->toLocation->id;
                $costLayer->binLocation = $line->toBinLocation;
                $costLayer->quantityReceived = $quantityToReceive;
                $costLayer->quantityRemaining = $quantityToReceive;
                $costLayer->unitCost = $line->unitCost; // Preserve FIFO cost from source
                $costLayer->originalUnitCost = $line->unitCost;
                $costLayer->landedCostAdjustments = 0.0;
                $costLayer->receiptDate = new \DateTime();
                $costLayer->transferReference = $transfer->transferNumber;
                
                $this->entityManager->persist($costLayer);

                // Record received quantity
                $line->recordReceived($quantityToReceive);

                // Update inventory balance at destination
                $this->inventoryBalanceService->updateBalance(
                    $line->item->id,
                    $transfer->toLocation->id,
                    $quantityToReceive,
                    'transit_in',
                    $line->toBinLocation
                );
            }

            // Mark as received
            $transfer->markAsReceived($receivedBy);

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $transfer;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Cancel a transfer
     *
     * @param int $transferId
     * @param string $reason
     * @return InventoryTransfer
     */
    public function cancelTransfer(int $transferId, string $reason): InventoryTransfer
    {
        $transfer = $this->transferRepository->find($transferId);
        if (!$transfer) {
            throw new \InvalidArgumentException("Transfer with ID {$transferId} not found");
        }

        $transfer->cancel();
        $transfer->notes = ($transfer->notes ? $transfer->notes . "\n\n" : '') . "Cancelled: {$reason}";

        $this->entityManager->flush();

        return $transfer;
    }

    /**
     * Calculate transfer cost
     *
     * @param int $transferId
     * @return float
     */
    public function calculateTransferCost(int $transferId): float
    {
        $transfer = $this->transferRepository->find($transferId);
        if (!$transfer) {
            throw new \InvalidArgumentException("Transfer with ID {$transferId} not found");
        }

        return $transfer->getTotalCost();
    }

    /**
     * Validate transfer is possible
     *
     * @param Location $fromLocation
     * @param Location $toLocation
     * @param array<int, array<string, mixed>> $lines
     * @return void
     */
    private function validateTransfer(Location $fromLocation, Location $toLocation, array $lines): void
    {
        // Check source location can transfer out
        if (!$fromLocation->isTransferSource) {
            throw new \InvalidArgumentException("Location {$fromLocation->locationName} cannot transfer out inventory");
        }

        // Check destination location can receive
        if (!$toLocation->isTransferDestination) {
            throw new \InvalidArgumentException("Location {$toLocation->locationName} cannot receive transferred inventory");
        }

        // Check both locations are active
        if (!$fromLocation->active) {
            throw new \InvalidArgumentException("Source location {$fromLocation->locationName} is not active");
        }

        if (!$toLocation->active) {
            throw new \InvalidArgumentException("Destination location {$toLocation->locationName} is not active");
        }

        // Validate lines
        if (empty($lines)) {
            throw new \InvalidArgumentException('Transfer must have at least one line item');
        }
    }
}
