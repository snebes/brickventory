<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ItemReceipt;
use App\Entity\VendorBill;
use App\Entity\VendorBillLine;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing Vendor Bill business logic and three-way matching
 */
class VendorBillService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Create vendor bill from item receipt
     */
    public function createBillFromReceipt(
        ItemReceipt $receipt,
        string $vendorInvoiceNumber = null,
        \DateTimeInterface $vendorInvoiceDate = null
    ): VendorBill {
        $bill = new VendorBill();
        $bill->billNumber = 'BILL-' . date('YmdHis');
        $bill->vendor = $receipt->vendor ?? $receipt->purchaseOrder->vendor;
        $bill->vendorInvoiceNumber = $vendorInvoiceNumber;
        $bill->vendorInvoiceDate = $vendorInvoiceDate;
        $bill->purchaseOrder = $receipt->purchaseOrder;
        $bill->itemReceipt = $receipt;
        $bill->paymentTerms = $receipt->purchaseOrder->paymentTerms 
            ?? $bill->vendor->defaultPaymentTerms;

        // Calculate due date based on payment terms
        $this->calculateDueDate($bill);

        $this->entityManager->persist($bill);

        // Create bill lines from receipt lines
        foreach ($receipt->lines as $receiptLine) {
            $billLine = new VendorBillLine();
            $billLine->vendorBill = $bill;
            $billLine->lineType = 'Item';
            $billLine->item = $receiptLine->item;
            $billLine->receiptLine = $receiptLine;
            $billLine->poLine = $receiptLine->purchaseOrderLine;
            $billLine->description = $receiptLine->item->itemName;
            $billLine->quantity = $receiptLine->quantityAccepted;
            $billLine->unitCost = $receiptLine->unitCost;
            $billLine->calculateAmount();

            $bill->lines->add($billLine);
            $this->entityManager->persist($billLine);
        }

        // Add freight as separate line if present
        if ($receipt->freightCost > 0) {
            $freightLine = new VendorBillLine();
            $freightLine->vendorBill = $bill;
            $freightLine->lineType = 'Expense';
            $freightLine->description = 'Freight - ' . ($receipt->landedCostCategory ?? 'Shipping');
            $freightLine->quantity = 1;
            $freightLine->unitCost = $receipt->freightCost;
            $freightLine->calculateAmount();

            $bill->lines->add($freightLine);
            $this->entityManager->persist($freightLine);
        }

        $bill->calculateTotals();
        $this->entityManager->flush();

        return $bill;
    }

    /**
     * Perform three-way matching (PO vs Receipt vs Bill)
     */
    public function performThreeWayMatch(VendorBill $bill, float $qtyTolerance = 5.0, float $priceTolerance = 5.0): array
    {
        $results = [];
        $requiresApproval = false;

        foreach ($bill->lines as $billLine) {
            if ($billLine->lineType !== 'Item') {
                continue; // Skip expense lines
            }

            $poLine = $billLine->poLine;
            $receiptLine = $billLine->receiptLine;

            if (!$poLine || !$receiptLine) {
                $results[] = [
                    'lineId' => $billLine->id,
                    'error' => 'Missing PO or Receipt line reference',
                    'approvalRequired' => true,
                ];
                $requiresApproval = true;
                continue;
            }

            // Check quantity match (Bill vs Receipt)
            $qtyVariance = abs($billLine->quantity - $receiptLine->quantityAccepted);
            $qtyVariancePct = $receiptLine->quantityAccepted > 0 
                ? ($qtyVariance / $receiptLine->quantityAccepted * 100) 
                : 0;
            $qtyMatch = $qtyVariancePct <= $qtyTolerance;

            // Check price match (Bill vs PO)
            $priceVariance = abs($billLine->unitCost - $poLine->rate);
            $priceVariancePct = $poLine->rate > 0 
                ? ($priceVariance / $poLine->rate * 100) 
                : 0;
            $priceMatch = $priceVariancePct <= $priceTolerance;

            // Check if bill matches receipt (using tolerance for float comparison)
            $receiptMatch = (abs($billLine->quantity - $receiptLine->quantityAccepted) < 0.001);

            $lineRequiresApproval = !($qtyMatch && $priceMatch && $receiptMatch);

            // Record variance if exists
            if (!$priceMatch) {
                $billLine->varianceAmount = ($billLine->unitCost - $poLine->rate) * $billLine->quantity;
            }

            $results[] = [
                'lineId' => $billLine->id,
                'item' => $billLine->item->itemName,
                'qtyMatch' => $qtyMatch,
                'qtyVariancePct' => $qtyVariancePct,
                'priceMatch' => $priceMatch,
                'priceVariancePct' => $priceVariancePct,
                'receiptMatch' => $receiptMatch,
                'approvalRequired' => $lineRequiresApproval,
                'billQty' => $billLine->quantity,
                'receiptQty' => $receiptLine->quantityAccepted,
                'poQty' => $poLine->quantityOrdered,
                'billPrice' => $billLine->unitCost,
                'poPrice' => $poLine->rate,
            ];

            if ($lineRequiresApproval) {
                $requiresApproval = true;
            }
        }

        return [
            'results' => $results,
            'requiresApproval' => $requiresApproval,
        ];
    }

    /**
     * Approve vendor bill
     */
    public function approveBill(VendorBill $bill, int $approverId): void
    {
        if ($bill->status !== 'Open') {
            throw new \RuntimeException('Bill is not in Open status');
        }

        $bill->approvedBy = $approverId;
        $bill->approvedAt = new \DateTime();

        $this->entityManager->flush();
    }

    /**
     * Calculate due date based on payment terms
     */
    private function calculateDueDate(VendorBill $bill): void
    {
        $terms = $bill->paymentTerms;
        
        // Parse common terms like "Net 30", "Net 60", "2/10 Net 30"
        if (preg_match('/Net\s+(\d+)/i', $terms ?? '', $matches)) {
            $days = (int) $matches[1];
            $bill->dueDate = (clone $bill->billDate)->modify("+{$days} days");
        } else {
            // Default to 30 days
            $bill->dueDate = (clone $bill->billDate)->modify('+30 days');
        }
    }
}
