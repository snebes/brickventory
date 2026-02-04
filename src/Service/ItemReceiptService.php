<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CostLayer;
use App\Entity\Item;
use App\Entity\ItemReceipt;
use App\Entity\ItemReceiptLine;
use App\Entity\PurchaseOrder;
use App\Event\ItemReceivedEvent;
use App\Service\InventoryBalanceService;
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
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly InventoryBalanceService $inventoryBalanceService
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
        // Get location from receipt or use default
        $location = $receiptLine->itemReceipt->location;
        if (!$location) {
            throw new \InvalidArgumentException('Receipt must have a receiving location');
        }

        // Create cost layer for FIFO tracking
        $costLayer = new CostLayer();
        $costLayer->item = $item;
        $costLayer->itemReceiptLine = $receiptLine;
        $costLayer->locationId = $location->id;
        $costLayer->binLocation = $receiptLine->binLocation;
        $costLayer->quantityReceived = $quantity;
        $costLayer->quantityRemaining = $quantity;
        $costLayer->unitCost = $unitCost;
        $costLayer->originalUnitCost = $unitCost;
        $costLayer->landedCostAdjustments = 0.0;
        $costLayer->receiptDate = $receiptLine->itemReceipt->getReceiptDate();

        // Set vendor if available
        if ($receiptLine->itemReceipt->vendor) {
            $costLayer->vendor = $receiptLine->itemReceipt->vendor;
        }

        $this->entityManager->persist($costLayer);

        // Link cost layer to receipt line
        $receiptLine->costLayer = $costLayer;

        // Update inventory balance at location (NEW: location-specific tracking)
        $this->inventoryBalanceService->updateBalance(
            $item->id,
            $location->id,
            $quantity,
            'receipt',
            $receiptLine->binLocation
        );

        // DEPRECATED: Update item quantities (for backward compatibility)
        // These will eventually be removed in favor of location-specific balances
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
        float $freightCost = 0.0,
        ?int $receivedAtLocationId = null
    ): ItemReceipt {
        // Validate PO can be received
        $this->purchaseOrderService->validatePOForReceipt($po);

        // Determine receiving location (from parameter, PO, or default)
        $receivingLocation = null;
        if ($receivedAtLocationId) {
            $receivingLocation = $this->entityManager->getRepository(\App\Entity\Location::class)
                ->find($receivedAtLocationId);
        } elseif ($po->location) {
            $receivingLocation = $po->location;
        }

        if (!$receivingLocation) {
            // Get default location
            $receivingLocation = $this->entityManager->getRepository(\App\Entity\Location::class)
                ->findOneBy(['locationCode' => 'DEFAULT']);
        }

        if (!$receivingLocation) {
            throw new \InvalidArgumentException('No receiving location specified and default location not found');
        }

        $receipt = new ItemReceipt();
        $receipt->purchaseOrder = $po;
        $receipt->vendor = $po->vendor;
        $receipt->location = $receivingLocation;
        $receipt->freightCost = $freightCost;

        $this->entityManager->persist($receipt);

        // Process receipt lines
        foreach ($lines as $lineData) {
            $poLine = $this->entityManager->getRepository(PurchaseOrderLine::class)
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
