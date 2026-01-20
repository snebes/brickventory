<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CostLayer;
use App\Entity\Item;
use App\Entity\InventoryAdjustmentLine;
use App\Entity\LayerConsumption;
use App\Repository\CostLayerRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing FIFO cost layers.
 * Handles creation, consumption, and querying of cost layers for inventory valuation.
 */
class FIFOLayerService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CostLayerRepository $costLayerRepository
    ) {
    }

    /**
     * Create a new cost layer from an inventory adjustment (for inventory increases)
     *
     * @param InventoryAdjustmentLine $adjustmentLine The adjustment line that increased inventory
     * @param float $unitCost Cost per unit for the new layer
     * @return CostLayer The created cost layer
     */
    public function createLayerFromAdjustment(
        InventoryAdjustmentLine $adjustmentLine,
        float $unitCost
    ): CostLayer {
        if ($adjustmentLine->quantityChange <= 0) {
            throw new \InvalidArgumentException('Can only create layers for inventory increases (positive quantity)');
        }

        $layer = new CostLayer();
        $layer->item = $adjustmentLine->item;
        $layer->layerType = CostLayer::TYPE_ADJUSTMENT;
        $layer->quantityReceived = $adjustmentLine->quantityChange;
        $layer->quantityRemaining = $adjustmentLine->quantityChange;
        $layer->unitCost = $unitCost;
        $layer->sourceType = 'inventory_adjustment';
        $layer->sourceReference = $adjustmentLine->inventoryAdjustment->adjustmentNumber;
        $layer->qualityStatus = CostLayer::QUALITY_AVAILABLE;
        
        $this->entityManager->persist($layer);
        
        return $layer;
    }

    /**
     * Consume layers using FIFO (First In, First Out) method
     *
     * @param Item $item The item to consume layers for
     * @param int|null $locationId Optional location ID for filtering
     * @param int $quantity Quantity to consume
     * @param string $transactionType Type of transaction consuming the layers
     * @param int $transactionId ID of the transaction
     * @return array{totalCost: float, layersConsumed: array<int, array{layerId: int, quantity: int, cost: float}>}
     */
    public function consumeLayersFIFO(
        Item $item,
        ?int $locationId,
        int $quantity,
        string $transactionType,
        int $transactionId
    ): array {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity to consume must be positive');
        }

        // Get available layers ordered by FIFO (oldest first)
        $layers = $this->getLayersByItem($item, $locationId, 'fifo');
        
        $remainingToConsume = $quantity;
        $totalCost = 0.0;
        $layersConsumed = [];
        
        foreach ($layers as $layer) {
            if ($remainingToConsume <= 0) {
                break;
            }
            
            if ($layer->quantityRemaining <= 0) {
                continue;
            }
            
            // Consume from this layer
            $result = $layer->consume($remainingToConsume);
            $consumedQty = $result['consumed'];
            $cost = $result['cost'];
            
            // Track the consumption
            $consumption = new LayerConsumption();
            $consumption->costLayer = $layer;
            $consumption->transactionType = $transactionType;
            $consumption->transactionId = $transactionId;
            $consumption->quantityConsumed = $consumedQty;
            $consumption->unitCost = $layer->unitCost;
            $consumption->totalCost = $cost;
            
            $this->entityManager->persist($consumption);
            $this->entityManager->persist($layer);
            
            $layersConsumed[] = [
                'layerId' => $layer->id,
                'quantity' => $consumedQty,
                'cost' => $cost,
                'unitCost' => $layer->unitCost,
            ];
            
            $totalCost += $cost;
            $remainingToConsume -= $consumedQty;
        }
        
        if ($remainingToConsume > 0) {
            // Not enough inventory in layers - this indicates a data inconsistency
            throw new \RuntimeException(
                sprintf(
                    'Insufficient inventory in cost layers for item %s. Needed %d more units.',
                    $item->itemId,
                    $remainingToConsume
                )
            );
        }
        
        return [
            'totalCost' => $totalCost,
            'layersConsumed' => $layersConsumed,
        ];
    }

    /**
     * Adjust the cost of an existing layer (for cost revaluations)
     *
     * @param int $layerId ID of the layer to adjust
     * @param float $newUnitCost New unit cost for the layer
     * @param string $reason Reason for the cost adjustment
     */
    public function adjustLayerCost(int $layerId, float $newUnitCost, string $reason): void
    {
        $layer = $this->costLayerRepository->find($layerId);
        
        if (!$layer) {
            throw new \InvalidArgumentException("Cost layer with ID {$layerId} not found");
        }
        
        if ($layer->voided) {
            throw new \InvalidArgumentException("Cannot adjust voided cost layer");
        }
        
        $layer->unitCost = $newUnitCost;
        $layer->sourceReference = $reason;
        
        $this->entityManager->persist($layer);
    }

    /**
     * Calculate weighted average cost for an item
     *
     * @param Item $item The item to calculate average cost for
     * @param int|null $locationId Optional location ID for filtering
     * @return float Weighted average cost per unit
     */
    public function getAverageCost(Item $item, ?int $locationId = null): float
    {
        $layers = $this->getLayersByItem($item, $locationId);
        
        $totalValue = 0.0;
        $totalQuantity = 0;
        
        foreach ($layers as $layer) {
            if ($layer->quantityRemaining > 0 && !$layer->voided) {
                $totalValue += $layer->quantityRemaining * $layer->unitCost;
                $totalQuantity += $layer->quantityRemaining;
            }
        }
        
        if ($totalQuantity === 0) {
            return 0.0;
        }
        
        return $totalValue / $totalQuantity;
    }

    /**
     * Get cost layers for an item
     *
     * @param Item $item The item to get layers for
     * @param int|null $locationId Optional location ID for filtering
     * @param string $orderBy Order by 'fifo' (oldest first) or 'lifo' (newest first)
     * @return CostLayer[] Array of cost layers
     */
    public function getLayersByItem(
        Item $item,
        ?int $locationId = null,
        string $orderBy = 'fifo'
    ): array {
        $qb = $this->costLayerRepository->createQueryBuilder('cl')
            ->where('cl.item = :item')
            ->andWhere('cl.quantityRemaining > 0')
            ->andWhere('cl.voided = false')
            ->andWhere('cl.qualityStatus = :qualityStatus')
            ->setParameter('item', $item)
            ->setParameter('qualityStatus', CostLayer::QUALITY_AVAILABLE);
        
        // Add location filter if provided
        // Note: locationId is not yet in CostLayer entity, but we're preparing for it
        
        // Order by receipt date for FIFO/LIFO
        if ($orderBy === 'fifo') {
            $qb->orderBy('cl.receiptDate', 'ASC');
        } elseif ($orderBy === 'lifo') {
            $qb->orderBy('cl.receiptDate', 'DESC');
        }
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Get total inventory value for an item based on cost layers
     *
     * @param Item $item The item to calculate value for
     * @param int|null $locationId Optional location ID for filtering
     * @return float Total inventory value
     */
    public function getTotalInventoryValue(Item $item, ?int $locationId = null): float
    {
        $layers = $this->getLayersByItem($item, $locationId);
        
        $totalValue = 0.0;
        foreach ($layers as $layer) {
            $totalValue += $layer->getTotalCost();
        }
        
        return $totalValue;
    }

    /**
     * Void a cost layer (mark as unusable)
     *
     * @param int $layerId ID of the layer to void
     * @param string $reason Reason for voiding
     */
    public function voidLayer(int $layerId, string $reason): void
    {
        $layer = $this->costLayerRepository->find($layerId);
        
        if (!$layer) {
            throw new \InvalidArgumentException("Cost layer with ID {$layerId} not found");
        }
        
        $layer->voided = true;
        $layer->voidReason = $reason;
        
        $this->entityManager->persist($layer);
    }
}
