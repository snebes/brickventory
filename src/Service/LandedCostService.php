<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ItemReceipt;
use App\Entity\LandedCost;
use App\Entity\LandedCostAllocation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing Landed Cost allocation to inventory
 */
class LandedCostService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Apply landed cost to an item receipt
     */
    public function applyLandedCost(
        ItemReceipt $receipt,
        string $costCategory,
        float $totalCost,
        string $allocationMethod = 'Value'
    ): LandedCost {
        $landedCost = new LandedCost();
        $landedCost->landedCostNumber = 'LC-' . date('YmdHis') . '-' . $receipt->id;
        $landedCost->itemReceipt = $receipt;
        $landedCost->costCategory = $costCategory;
        $landedCost->totalCost = $totalCost;
        $landedCost->allocationMethod = $allocationMethod;

        $this->entityManager->persist($landedCost);

        // Allocate cost based on method
        $allocations = match ($allocationMethod) {
            'Quantity' => $this->allocateByQuantity($receipt, $totalCost),
            'Weight' => $this->allocateByWeight($receipt, $totalCost),
            default => $this->allocateByValue($receipt, $totalCost),
        };

        // Create allocation records and update cost layers
        foreach ($allocations as $allocation) {
            $this->entityManager->persist($allocation);
            $landedCost->allocations->add($allocation);

            // Update the cost layer
            $this->updateLayerCost($allocation);
        }

        $this->entityManager->flush();

        return $landedCost;
    }

    /**
     * Allocate landed cost by value (item cost * quantity)
     */
    private function allocateByValue(ItemReceipt $receipt, float $totalCost): array
    {
        $allocations = [];
        $totalValue = 0.0;

        // Calculate total value
        foreach ($receipt->lines as $line) {
            if ($line->costLayer && $line->quantityAccepted > 0) {
                $totalValue += $line->unitCost * $line->quantityAccepted;
            }
        }

        if ($totalValue <= 0) {
            throw new \RuntimeException('Cannot allocate landed cost: total value is zero');
        }

        // Allocate proportionally
        foreach ($receipt->lines as $line) {
            if ($line->costLayer && $line->quantityAccepted > 0) {
                $lineValue = $line->unitCost * $line->quantityAccepted;
                $percentage = $lineValue / $totalValue;
                $allocatedAmount = $totalCost * $percentage;

                $allocation = new LandedCostAllocation();
                $allocation->landedCost = null; // Will be set by caller
                $allocation->receiptLine = $line;
                $allocation->costLayer = $line->costLayer;
                $allocation->item = $line->item;
                $allocation->allocatedAmount = $allocatedAmount;
                $allocation->allocationPercentage = $percentage;
                $allocation->quantity = $line->quantityAccepted;
                $allocation->originalUnitCost = $line->costLayer->originalUnitCost;
                $allocation->adjustedUnitCost = $line->costLayer->originalUnitCost + 
                    ($allocatedAmount / $line->quantityAccepted);

                $allocations[] = $allocation;
            }
        }

        return $allocations;
    }

    /**
     * Allocate landed cost by quantity
     */
    private function allocateByQuantity(ItemReceipt $receipt, float $totalCost): array
    {
        $allocations = [];
        $totalQuantity = 0;

        // Calculate total quantity
        foreach ($receipt->lines as $line) {
            if ($line->costLayer && $line->quantityAccepted > 0) {
                $totalQuantity += $line->quantityAccepted;
            }
        }

        if ($totalQuantity <= 0) {
            throw new \RuntimeException('Cannot allocate landed cost: total quantity is zero');
        }

        // Allocate proportionally
        foreach ($receipt->lines as $line) {
            if ($line->costLayer && $line->quantityAccepted > 0) {
                $percentage = $line->quantityAccepted / $totalQuantity;
                $allocatedAmount = $totalCost * $percentage;

                $allocation = new LandedCostAllocation();
                $allocation->receiptLine = $line;
                $allocation->costLayer = $line->costLayer;
                $allocation->item = $line->item;
                $allocation->allocatedAmount = $allocatedAmount;
                $allocation->allocationPercentage = $percentage;
                $allocation->quantity = $line->quantityAccepted;
                $allocation->originalUnitCost = $line->costLayer->originalUnitCost;
                $allocation->adjustedUnitCost = $line->costLayer->originalUnitCost + 
                    ($allocatedAmount / $line->quantityAccepted);

                $allocations[] = $allocation;
            }
        }

        return $allocations;
    }

    /**
     * Allocate landed cost by weight (requires item weight data)
     */
    private function allocateByWeight(ItemReceipt $receipt, float $totalCost): array
    {
        // For now, fall back to quantity-based allocation
        // In a full implementation, this would use item.weight * quantity
        return $this->allocateByQuantity($receipt, $totalCost);
    }

    /**
     * Update cost layer with allocated landed cost
     */
    private function updateLayerCost(LandedCostAllocation $allocation): void
    {
        $costLayer = $allocation->costLayer;
        $perUnitAdjustment = $allocation->allocatedAmount / $allocation->quantity;

        $costLayer->applyLandedCost($perUnitAdjustment);
    }
}
