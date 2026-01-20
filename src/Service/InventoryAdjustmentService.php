<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InventoryAdjustment;
use App\Entity\InventoryAdjustmentLine;
use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\Location;
use App\Event\InventoryAdjustedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for managing inventory adjustments.
 * Handles creation, posting, reversal, and approval of inventory adjustments.
 */
class InventoryAdjustmentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FIFOLayerService $fifoLayerService
    ) {
    }

    /**
     * Create a quantity adjustment
     *
     * @param int $locationId Location ID for the adjustment (required - follows NetSuite ERP pattern)
     * @param array<array{itemId: int, quantityChange: int, unitCost?: float, notes?: string}> $lines Adjustment line data
     * @param string $reasonCode Reason code for the adjustment
     * @param string|null $memo Optional memo
     * @param bool $autoPost Whether to automatically post the adjustment
     * @return InventoryAdjustment The created adjustment
     */
    public function createQuantityAdjustment(
        int $locationId,
        array $lines,
        string $reasonCode,
        ?string $memo = null,
        bool $autoPost = false
    ): InventoryAdjustment {
        if (empty($lines)) {
            throw new \InvalidArgumentException('At least one adjustment line is required');
        }

        // Location is required - following NetSuite ERP pattern
        $location = $this->entityManager->getRepository(Location::class)->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        if (!$location->active) {
            throw new \InvalidArgumentException("Location '{$location->locationName}' is inactive and cannot be used for adjustments");
        }

        $this->entityManager->beginTransaction();

        try {
            $adjustment = new InventoryAdjustment();
            $adjustment->adjustmentNumber = $this->generateAdjustmentNumber();
            $adjustment->adjustmentType = InventoryAdjustment::TYPE_QUANTITY_ADJUSTMENT;
            $adjustment->reason = $reasonCode;
            $adjustment->memo = $memo;
            $adjustment->location = $location;
            $adjustment->status = InventoryAdjustment::STATUS_DRAFT;
            
            $totalQuantityChange = 0.0;
            
            foreach ($lines as $lineData) {
                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                
                if (!$item) {
                    throw new \InvalidArgumentException("Item with ID {$lineData['itemId']} not found");
                }
                
                $line = new InventoryAdjustmentLine();
                $line->inventoryAdjustment = $adjustment;
                $line->item = $item;
                $line->adjustmentType = InventoryAdjustmentLine::TYPE_QUANTITY;
                $line->quantityChange = $lineData['quantityChange'];
                $line->quantityBefore = (float)$item->quantityOnHand;
                $line->quantityAfter = $line->quantityBefore + $lineData['quantityChange'];
                $line->notes = $lineData['notes'] ?? null;
                
                if (isset($lineData['unitCost'])) {
                    $line->currentUnitCost = $lineData['unitCost'];
                    $line->totalCostImpact = $lineData['quantityChange'] * $lineData['unitCost'];
                }
                
                $adjustment->lines->add($line);
                $totalQuantityChange += $lineData['quantityChange'];
            }
            
            $adjustment->totalQuantityChange = $totalQuantityChange;
            
            $this->entityManager->persist($adjustment);
            $this->entityManager->flush();

            if ($autoPost) {
                $this->postAdjustment($adjustment->id);
            }

            $this->entityManager->commit();
            
            return $adjustment;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Post an adjustment to inventory and update cost layers
     *
     * @param int $adjustmentId ID of the adjustment to post
     */
    public function postAdjustment(int $adjustmentId): void
    {
        $adjustment = $this->entityManager->getRepository(InventoryAdjustment::class)->find($adjustmentId);
        
        if (!$adjustment) {
            throw new \InvalidArgumentException("Adjustment with ID {$adjustmentId} not found");
        }

        if (!$adjustment->canBePosted()) {
            throw new \InvalidArgumentException(
                "Adjustment cannot be posted. Current status: {$adjustment->status}"
            );
        }

        $this->entityManager->beginTransaction();

        try {
            // Process each line
            foreach ($adjustment->lines as $line) {
                if ($line->quantityChange > 0) {
                    // Inventory increase - create new cost layer
                    $this->createAdjustmentIncrease($line);
                } elseif ($line->quantityChange < 0) {
                    // Inventory decrease - consume FIFO layers
                    $this->createAdjustmentDecrease($line);
                }
                
                // Dispatch inventory adjusted event to update item quantities
                $event = new InventoryAdjustedEvent(
                    $line->item,
                    $line->quantityChange,
                    $adjustment
                );
                $this->eventDispatcher->dispatch($event);
            }

            // Update adjustment status
            $adjustment->status = InventoryAdjustment::STATUS_POSTED;
            $adjustment->postedAt = new \DateTime();
            $adjustment->postedBy = 'system'; // TODO: Get from security context
            
            $this->entityManager->persist($adjustment);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Reverse an adjustment by creating an offsetting adjustment
     *
     * @param int $adjustmentId ID of the adjustment to reverse
     * @param string $reason Reason for reversal
     * @return InventoryAdjustment The reversing adjustment
     */
    public function reverseAdjustment(int $adjustmentId, string $reason): InventoryAdjustment
    {
        $originalAdjustment = $this->entityManager->getRepository(InventoryAdjustment::class)->find($adjustmentId);
        
        if (!$originalAdjustment) {
            throw new \InvalidArgumentException("Adjustment with ID {$adjustmentId} not found");
        }

        if (!$originalAdjustment->isPosted()) {
            throw new \InvalidArgumentException('Only posted adjustments can be reversed');
        }

        $this->entityManager->beginTransaction();

        try {
            // Create reversing adjustment
            $reversingAdjustment = new InventoryAdjustment();
            $reversingAdjustment->adjustmentNumber = $this->generateAdjustmentNumber();
            $reversingAdjustment->adjustmentType = $originalAdjustment->adjustmentType;
            $reversingAdjustment->reason = 'Reversal: ' . $reason;
            $reversingAdjustment->memo = "Reversal of {$originalAdjustment->adjustmentNumber}. Reason: {$reason}";
            $reversingAdjustment->referenceNumber = $originalAdjustment->adjustmentNumber;
            $reversingAdjustment->location = $originalAdjustment->location;
            $reversingAdjustment->status = InventoryAdjustment::STATUS_DRAFT;

            // Create reversing lines with opposite quantities
            foreach ($originalAdjustment->lines as $originalLine) {
                $reversingLine = new InventoryAdjustmentLine();
                $reversingLine->inventoryAdjustment = $reversingAdjustment;
                $reversingLine->item = $originalLine->item;
                $reversingLine->adjustmentType = $originalLine->adjustmentType;
                $reversingLine->quantityChange = -$originalLine->quantityChange; // Opposite sign
                $reversingLine->currentUnitCost = $originalLine->currentUnitCost;
                $reversingLine->totalCostImpact = -$originalLine->totalCostImpact;
                $reversingLine->notes = "Reversal of adjustment line";
                
                $reversingAdjustment->lines->add($reversingLine);
            }

            $reversingAdjustment->totalQuantityChange = -$originalAdjustment->totalQuantityChange;
            $reversingAdjustment->totalValueChange = -$originalAdjustment->totalValueChange;

            $this->entityManager->persist($reversingAdjustment);
            $this->entityManager->flush();

            // Auto-post the reversing adjustment
            $this->postAdjustment($reversingAdjustment->id);

            $this->entityManager->commit();

            return $reversingAdjustment;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Approve a pending adjustment
     *
     * @param int $adjustmentId ID of the adjustment to approve
     * @param string $approverId ID of the approver
     */
    public function approveAdjustment(int $adjustmentId, string $approverId): void
    {
        $adjustment = $this->entityManager->getRepository(InventoryAdjustment::class)->find($adjustmentId);
        
        if (!$adjustment) {
            throw new \InvalidArgumentException("Adjustment with ID {$adjustmentId} not found");
        }

        if (!$adjustment->canBeApproved()) {
            throw new \InvalidArgumentException(
                "Adjustment cannot be approved. Current status: {$adjustment->status}"
            );
        }

        $adjustment->status = InventoryAdjustment::STATUS_APPROVED;
        $adjustment->approvedBy = $approverId;
        $adjustment->approvedAt = new \DateTime();

        $this->entityManager->persist($adjustment);
        $this->entityManager->flush();
    }

    /**
     * Create adjustment increase (add inventory with new cost layer)
     */
    private function createAdjustmentIncrease(InventoryAdjustmentLine $line): void
    {
        // Determine unit cost for the new layer
        $unitCost = $line->adjustmentUnitCost ?? $line->currentUnitCost ?? 0.0;
        
        if ($unitCost > 0) {
            // Create new cost layer for the increased inventory
            $layer = $this->fifoLayerService->createLayerFromAdjustment($line, $unitCost);
            
            // Track which layer was created
            $line->layersAffected = [$layer->id];
            $this->entityManager->persist($line);
        }
    }

    /**
     * Create adjustment decrease (remove inventory using FIFO)
     */
    private function createAdjustmentDecrease(InventoryAdjustmentLine $line): void
    {
        $quantityToConsume = abs($line->quantityChange);
        
        // Consume layers using FIFO - use location from header
        $result = $this->fifoLayerService->consumeLayersFIFO(
            $line->item,
            $line->inventoryAdjustment->location->id,
            $quantityToConsume,
            'inventory_adjustment',
            $line->inventoryAdjustment->id
        );
        
        // Update line with cost information
        $line->totalCostImpact = -$result['totalCost']; // Negative because it's a decrease
        $line->layersAffected = array_column($result['layersConsumed'], 'layerId');
        
        $this->entityManager->persist($line);
    }

    /**
     * Create a cost revaluation adjustment
     *
     * @param int $itemId Item ID
     * @param int $locationId Location ID (required - follows NetSuite ERP pattern)
     * @param float $newUnitCost New unit cost
     * @param string $reason Reason for the revaluation
     * @return InventoryAdjustment The created adjustment
     */
    public function createCostRevaluation(
        int $itemId,
        int $locationId,
        float $newUnitCost,
        string $reason
    ): InventoryAdjustment {
        $item = $this->entityManager->getRepository(Item::class)->find($itemId);
        
        if (!$item) {
            throw new \InvalidArgumentException("Item with ID {$itemId} not found");
        }

        // Location is required - following NetSuite ERP pattern
        $location = $this->entityManager->getRepository(Location::class)->find($locationId);
        if (!$location) {
            throw new \InvalidArgumentException("Location with ID {$locationId} not found");
        }

        if (!$location->active) {
            throw new \InvalidArgumentException("Location '{$location->locationName}' is inactive and cannot be used for adjustments");
        }

        $adjustment = new InventoryAdjustment();
        $adjustment->adjustmentNumber = $this->generateAdjustmentNumber();
        $adjustment->adjustmentType = InventoryAdjustment::TYPE_COST_REVALUATION;
        $adjustment->reason = $reason;
        $adjustment->location = $location;
        $adjustment->status = InventoryAdjustment::STATUS_DRAFT;

        $line = new InventoryAdjustmentLine();
        $line->inventoryAdjustment = $adjustment;
        $line->item = $item;
        $line->adjustmentType = InventoryAdjustmentLine::TYPE_VALUE;
        $line->quantityChange = 0; // No quantity change in cost revaluation
        $line->currentUnitCost = $this->fifoLayerService->getAverageCost($item, $locationId);
        $line->newUnitCost = $newUnitCost;
        $line->totalCostImpact = ($newUnitCost - $line->currentUnitCost) * $item->quantityOnHand;
        
        $adjustment->lines->add($line);
        $adjustment->totalValueChange = $line->totalCostImpact;

        $this->entityManager->persist($adjustment);
        $this->entityManager->flush();

        return $adjustment;
    }

    /**
     * Create a write-down adjustment for obsolete inventory
     *
     * @param int $itemId Item ID
     * @param int $locationId Location ID (required - follows NetSuite ERP pattern)
     * @param float $writeDownPercent Write-down percentage (0-100)
     * @param string $reason Reason for the write-down
     * @return InventoryAdjustment The created adjustment
     */
    public function createWriteDown(
        int $itemId,
        int $locationId,
        float $writeDownPercent,
        string $reason
    ): InventoryAdjustment {
        if ($writeDownPercent < 0 || $writeDownPercent > 100) {
            throw new \InvalidArgumentException('Write-down percent must be between 0 and 100');
        }

        $item = $this->entityManager->getRepository(Item::class)->find($itemId);
        
        if (!$item) {
            throw new \InvalidArgumentException("Item with ID {$itemId} not found");
        }

        $currentCost = $this->fifoLayerService->getAverageCost($item, $locationId);
        $newCost = $currentCost * (1 - $writeDownPercent / 100);

        return $this->createCostRevaluation($itemId, $locationId, $newCost, "Write-down {$writeDownPercent}%: {$reason}");
    }

    /**
     * Generate a unique adjustment number
     */
    private function generateAdjustmentNumber(): string
    {
        return 'ADJ-' . date('YmdHis') . '-' . substr((string)microtime(true), -4);
    }
}
