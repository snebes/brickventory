<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Entity\Vendor;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing Purchase Order business logic
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Validate a purchase order according to NetSuite ERP rules
     * 
     * @throws \RuntimeException if validation fails
     */
    public function validatePurchaseOrder(PurchaseOrder $po): bool
    {
        // Vendor is required
        if (!isset($po->vendor)) {
            throw new \RuntimeException('Vendor is required for Purchase Order');
        }

        // Validate vendor exists
        $vendor = $this->entityManager->getRepository(Vendor::class)->find($po->vendor->id);
        if (!$vendor) {
            throw new \RuntimeException('Invalid vendor specified');
        }

        // Validate vendor is active
        if (!$vendor->active) {
            throw new \RuntimeException('Cannot create PO with inactive vendor');
        }

        // Validate multi-currency
        if ($po->currency && $vendor->defaultCurrency && $po->currency !== $vendor->defaultCurrency) {
            if (!$po->exchangeRate) {
                throw new \RuntimeException('Exchange rate required for multi-currency PO');
            }
        }

        return true;
    }

    /**
     * Approve a purchase order
     */
    public function approvePurchaseOrder(PurchaseOrder $po, int $approverId): void
    {
        if ($po->status !== 'Pending Approval') {
            throw new \RuntimeException('Purchase order is not in Pending Approval status');
        }

        // Validate PO before approval
        $this->validatePurchaseOrder($po);

        $po->status = 'Pending Receipt';
        $po->approvedBy = $approverId;
        $po->approvedAt = new \DateTime();

        $this->entityManager->flush();
    }

    /**
     * Close a purchase order
     */
    public function closePurchaseOrder(PurchaseOrder $po, string $reason): void
    {
        if ($po->status === 'Closed' || $po->status === 'Cancelled') {
            throw new \RuntimeException('Purchase order is already closed or cancelled');
        }

        $po->status = $reason === 'Cancelled' ? 'Cancelled' : 'Closed';

        // Close all lines
        foreach ($po->lines as $line) {
            if (!$line->closed) {
                $line->closed = true;
                $line->closedReason = $reason;
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Update PO status based on receipt quantities
     */
    public function updatePOStatus(PurchaseOrder $po): void
    {
        if ($po->status === 'Closed' || $po->status === 'Cancelled') {
            return; // Don't update closed/cancelled POs
        }

        $totalOrdered = 0;
        $totalReceived = 0;
        $allFullyReceived = true;
        $anyReceived = false;

        foreach ($po->lines as $line) {
            $totalOrdered += $line->quantityOrdered;
            $totalReceived += $line->quantityReceived;

            if ($line->quantityReceived > 0) {
                $anyReceived = true;
            }

            if ($line->quantityReceived < $line->quantityOrdered) {
                $allFullyReceived = false;
            }
        }

        if ($allFullyReceived && $anyReceived) {
            $po->status = 'Fully Received';
        } elseif ($anyReceived) {
            $po->status = 'Partially Received';
        } elseif ($po->status !== 'Pending Approval') {
            $po->status = 'Pending Receipt';
        }

        $this->entityManager->flush();
    }

    /**
     * Validate if PO can be received
     */
    public function validatePOForReceipt(PurchaseOrder $po): bool
    {
        // PO must be approved
        if ($po->status === 'Pending Approval') {
            throw new \RuntimeException('Purchase order must be approved before receiving');
        }

        // PO must not be closed or cancelled
        if ($po->status === 'Closed' || $po->status === 'Cancelled') {
            throw new \RuntimeException('Cannot receive against closed or cancelled purchase order');
        }

        // Check if vendor is active (if vendor is set)
        if ($po->vendor && !$po->vendor->active) {
            throw new \RuntimeException('Cannot receive from inactive vendor');
        }

        return true;
    }

    /**
     * Check if all lines are fully received
     */
    public function isFullyReceived(PurchaseOrder $po): bool
    {
        foreach ($po->lines as $line) {
            if ($line->quantityReceived < $line->quantityOrdered) {
                return false;
            }
        }

        return true;
    }
}
