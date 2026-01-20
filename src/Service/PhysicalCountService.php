<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Item;
use App\Entity\PhysicalCount;
use App\Entity\PhysicalCountLine;
use App\Entity\InventoryAdjustment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing physical inventory counts.
 * Handles creation, recording, and adjustment generation from physical counts.
 */
class PhysicalCountService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryAdjustmentService $adjustmentService
    ) {
    }

    /**
     * Create a new physical count
     *
     * @param int|null $locationId Location ID for the count
     * @param string $countType Type of count (full_physical, cycle_count, spot_count)
     * @param array<int> $itemIds Array of item IDs to count
     * @return PhysicalCount The created physical count
     */
    public function createPhysicalCount(
        ?int $locationId,
        string $countType,
        array $itemIds
    ): PhysicalCount {
        if (empty($itemIds)) {
            throw new \InvalidArgumentException('At least one item is required for physical count');
        }

        $count = new PhysicalCount();
        $count->countNumber = $this->generateCountNumber();
        $count->countType = $countType;
        $count->locationId = $locationId;
        $count->status = PhysicalCount::STATUS_PLANNED;

        foreach ($itemIds as $itemId) {
            $item = $this->entityManager->getRepository(Item::class)->find($itemId);
            
            if (!$item) {
                throw new \InvalidArgumentException("Item with ID {$itemId} not found");
            }

            $line = new PhysicalCountLine();
            $line->physicalCount = $count;
            $line->item = $item;
            $line->locationId = $locationId;
            $line->systemQuantity = (float)$item->quantityOnHand;
            
            $count->lines->add($line);
        }

        $this->entityManager->persist($count);
        $this->entityManager->flush();

        return $count;
    }

    /**
     * Record a count result for a specific line
     *
     * @param int $countLineId ID of the count line
     * @param float $countedQty Counted quantity
     * @param string $countedBy User who performed the count
     */
    public function recordCountResult(
        int $countLineId,
        float $countedQty,
        string $countedBy
    ): void {
        $line = $this->entityManager->getRepository(PhysicalCountLine::class)->find($countLineId);
        
        if (!$line) {
            throw new \InvalidArgumentException("Count line with ID {$countLineId} not found");
        }

        $line->countedQuantity = $countedQty;
        $line->countedBy = $countedBy;
        $line->countedAt = new \DateTime();
        
        // Calculate variance
        $line->calculateVariance();
        
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        // Check if all lines are counted and update count status
        $this->updateCountStatus($line->physicalCount);
    }

    /**
     * Create an inventory adjustment from a completed physical count
     *
     * @param int $countId ID of the physical count
     * @param bool $autoPost Whether to automatically post the adjustment
     * @return InventoryAdjustment|null The created adjustment, or null if no variances
     */
    public function createAdjustmentFromCount(
        int $countId,
        bool $autoPost = false
    ): ?InventoryAdjustment {
        $count = $this->entityManager->getRepository(PhysicalCount::class)->find($countId);
        
        if (!$count) {
            throw new \InvalidArgumentException("Physical count with ID {$countId} not found");
        }

        if (!$count->canCreateAdjustment()) {
            throw new \InvalidArgumentException(
                "Cannot create adjustment from count. Current status: {$count->status}"
            );
        }

        if (!$count->hasVariances()) {
            // No variances, no adjustment needed
            return null;
        }

        $this->entityManager->beginTransaction();

        try {
            $adjustmentLines = [];

            foreach ($count->lines as $line) {
                if ($line->hasVariance() && $line->isCounted()) {
                    $adjustmentLines[] = [
                        'itemId' => $line->item->id,
                        'quantityChange' => (int)$line->varianceQuantity,
                        'notes' => "Physical count {$count->countNumber} variance",
                    ];
                }
            }

            $adjustment = $this->adjustmentService->createQuantityAdjustment(
                $count->locationId,
                $adjustmentLines,
                'physical_count',
                "From physical count {$count->countNumber}",
                $autoPost
            );

            // Link adjustment lines to count lines
            $adjustmentLineIndex = 0;
            foreach ($count->lines as $line) {
                if ($line->hasVariance() && $line->isCounted()) {
                    $adjustmentLine = $adjustment->lines->get($adjustmentLineIndex);
                    if ($adjustmentLine) {
                        $line->adjustmentLine = $adjustmentLine;
                        $this->entityManager->persist($line);
                    }
                    $adjustmentLineIndex++;
                }
            }

            // Update count status
            $count->status = PhysicalCount::STATUS_ADJUSTMENT_CREATED;
            $this->entityManager->persist($count);

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $adjustment;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Setup cycle count schedule for an item
     *
     * @param int $itemId Item ID to schedule
     * @param int $frequencyDays How often to count (in days)
     * @param int|null $locationId Optional location ID
     */
    public function setupCycleCountSchedule(
        int $itemId,
        int $frequencyDays,
        ?int $locationId = null
    ): void {
        // TODO: Implement cycle count schedule tracking
        // This would require a CycleCountSchedule entity
        throw new \RuntimeException('Cycle count scheduling not yet implemented');
    }

    /**
     * Get items that are due for cycle counting
     *
     * @param int|null $locationId Optional location ID filter
     * @return array<Item> Array of items due for counting
     */
    public function getItemsDueForCycleCount(?int $locationId = null): array
    {
        // TODO: Implement based on cycle count schedule
        // For now, return empty array
        return [];
    }

    /**
     * Update the status of a physical count based on line completion
     */
    private function updateCountStatus(PhysicalCount $count): void
    {
        $totalLines = $count->lines->count();
        $countedLines = 0;

        foreach ($count->lines as $line) {
            if ($line->isCounted()) {
                $countedLines++;
            }
        }

        if ($countedLines === 0) {
            $count->status = PhysicalCount::STATUS_PLANNED;
        } elseif ($countedLines < $totalLines) {
            $count->status = PhysicalCount::STATUS_IN_PROGRESS;
        } else {
            $count->status = PhysicalCount::STATUS_COMPLETED;
            $count->completedAt = new \DateTime();
        }

        $this->entityManager->persist($count);
    }

    /**
     * Generate a unique count number
     */
    private function generateCountNumber(): string
    {
        return 'PC-' . date('YmdHis') . '-' . substr((string)microtime(true), -4);
    }
}
