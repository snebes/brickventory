<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CostLayer;
use App\Entity\Item;
use App\Entity\ItemReceipt;
use App\Entity\ItemReceiptLine;
use App\Entity\PurchaseOrder;
use App\Event\ItemReceivedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for managing Item Receipt business logic and FIFO cost layer creation
 */
class ItemReceiptService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PurchaseOrderService $purchaseOrderService
    ) {
    }

    /**
     * Receive inventory and create cost layers (FIFO)
     * This method is called when items are received from a purchase order
     */
    public function receiveInventory(
        ItemReceiptLine $receiptLine,
        Item $item,
        int $quantity,
        float $unitCost
    ): CostLayer {
        // Create cost layer for FIFO tracking
        $costLayer = new CostLayer();
        $costLayer->item = $item;
        $costLayer->itemReceiptLine = $receiptLine;
        $costLayer->quantityReceived = $quantity;
        $costLayer->quantityRemaining = $quantity;
        $costLayer->unitCost = $unitCost;
        $costLayer->originalUnitCost = $unitCost;
        $costLayer->landedCostAdjustments = 0.0;
        $costLayer->receiptDate = $receiptLine->itemReceipt->receiptDate;
        
        // Set vendor if available
        if ($receiptLine->itemReceipt->vendor) {
            $costLayer->vendor = $receiptLine->itemReceipt->vendor;
        }

        $this->entityManager->persist($costLayer);

        // Link cost layer to receipt line
        $receiptLine->costLayer = $costLayer;

        // Update item quantities
        $item->quantityOnHand += $quantity;
        $item->quantityOnOrder -= $quantity;
        $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;

        // Dispatch event for event sourcing
        $this->eventDispatcher->dispatch(
            new ItemReceivedEvent(
                $item,
                $quantity,
                $receiptLine->itemReceipt->purchaseOrder,
                $unitCost,
                $receiptLine
            )
        );

        return $costLayer;
    }

    /**
     * Create item receipt from purchase order
     */
    public function createItemReceipt(
        PurchaseOrder $po,
        array $lines,
        float $freightCost = 0.0
    ): ItemReceipt {
        // Validate PO can be received
        $this->purchaseOrderService->validatePOForReceipt($po);

        $receipt = new ItemReceipt();
        $receipt->purchaseOrder = $po;
        $receipt->vendor = $po->vendor;
        $receipt->freightCost = $freightCost;

        $this->entityManager->persist($receipt);

        // Process receipt lines
        foreach ($lines as $lineData) {
            $poLine = $this->entityManager->getRepository($lineData['poLineClass'] ?? 'App\Entity\PurchaseOrderLine')
                ->find($lineData['poLineId']);

            if (!$poLine) {
                throw new \InvalidArgumentException("PO Line {$lineData['poLineId']} not found");
            }

            $quantityToReceive = $lineData['quantityReceived'] ?? 0;

            // Validate quantity
            $remainingQty = $poLine->quantityOrdered - $poLine->quantityReceived;
            if ($quantityToReceive > $remainingQty) {
                throw new \InvalidArgumentException(
                    "Cannot receive {$quantityToReceive} items. Only {$remainingQty} remaining on PO line."
                );
            }

            $receiptLine = new ItemReceiptLine();
            $receiptLine->itemReceipt = $receipt;
            $receiptLine->item = $poLine->item;
            $receiptLine->purchaseOrderLine = $poLine;
            $receiptLine->quantityReceived = $quantityToReceive;
            $receiptLine->quantityAccepted = $lineData['quantityAccepted'] ?? $quantityToReceive;
            $receiptLine->quantityRejected = $lineData['quantityRejected'] ?? 0;
            $receiptLine->unitCost = $poLine->rate;
            $receiptLine->binLocation = $lineData['binLocation'] ?? null;
            $receiptLine->lotNumber = $lineData['lotNumber'] ?? null;
            $receiptLine->serialNumbers = $lineData['serialNumbers'] ?? null;
            $receiptLine->expirationDate = isset($lineData['expirationDate']) 
                ? new \DateTime($lineData['expirationDate']) 
                : null;
            $receiptLine->receivingNotes = $lineData['receivingNotes'] ?? null;

            $receipt->lines->add($receiptLine);
            $this->entityManager->persist($receiptLine);

            // Update PO line quantities
            $poLine->quantityReceived += $quantityToReceive;

            // Create cost layer for accepted quantity
            if ($receiptLine->quantityAccepted > 0) {
                $this->receiveInventory(
                    $receiptLine,
                    $poLine->item,
                    $receiptLine->quantityAccepted,
                    $poLine->rate
                );
            }
        }

        $this->entityManager->flush();

        // Update PO status
        $this->purchaseOrderService->updatePOStatus($po);

        return $receipt;
    }
}
