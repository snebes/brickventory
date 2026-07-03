<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BillPayment;
use App\Entity\BillPaymentApplication;
use App\Entity\Vendor;
use App\Entity\VendorBill;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing Bill Payment business logic
 */
class BillPaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Create payment and apply to bills
     */
    public function createPayment(
        Vendor $vendor,
        array $billApplications,
        string $paymentMethod = 'Check',
        \DateTimeInterface $paymentDate = null,
        string $checkNumber = null
    ): BillPayment {
        $payment = new BillPayment();
        $payment->paymentNumber = 'PAY-' . date('YmdHis');
        $payment->vendor = $vendor;
        $payment->paymentMethod = $paymentMethod;
        $payment->paymentDate = $paymentDate ?? new \DateTime();
        $payment->checkNumber = $checkNumber;
        $payment->currency = $vendor->defaultCurrency;

        $this->entityManager->persist($payment);

        $totalAmount = 0.0;
        $totalDiscount = 0.0;

        // Process each bill application
        foreach ($billApplications as $appData) {
            $bill = $this->entityManager->getRepository(VendorBill::class)
                ->find($appData['billId']);

            if (!$bill) {
                throw new \InvalidArgumentException("Bill {$appData['billId']} not found");
            }

            if ($bill->vendor->id !== $vendor->id) {
                throw new \InvalidArgumentException("Bill does not belong to vendor");
            }

            $amountToApply = $appData['amount'] ?? $bill->amountDue;
            $discount = $appData['discount'] ?? 0.0;

            // Calculate early payment discount if applicable
            if (!isset($appData['discount'])) {
                $discount = $this->calculateEarlyPaymentDiscount($bill, $payment->paymentDate);
            }

            // Apply payment to bill
            $this->applyPaymentToBill($payment, $bill, $amountToApply, $discount);

            $totalAmount += $amountToApply;
            $totalDiscount += $discount;
        }

        $payment->totalAmount = $totalAmount;
        $payment->discountTaken = $totalDiscount;
        $payment->status = 'Pending';

        $this->entityManager->flush();

        return $payment;
    }

    /**
     * Apply payment to a specific bill
     */
    public function applyPaymentToBill(
        BillPayment $payment,
        VendorBill $bill,
        float $amount,
        float $discount = 0.0
    ): BillPaymentApplication {
        if ($amount > $bill->amountDue) {
            throw new \InvalidArgumentException('Payment amount exceeds bill amount due');
        }

        $application = new BillPaymentApplication();
        $application->billPayment = $payment;
        $application->vendorBill = $bill;
        $application->amountApplied = $amount;
        $application->discountApplied = $discount;

        $payment->applications->add($application);
        $this->entityManager->persist($application);

        // Update bill
        $bill->applyPayment($amount, $discount);

        return $application;
    }

    /**
     * Calculate early payment discount (e.g., 2/10 Net 30)
     */
    public function calculateEarlyPaymentDiscount(
        VendorBill $bill,
        \DateTimeInterface $paymentDate
    ): float {
        $terms = $bill->paymentTerms;

        // Parse terms like "2/10 Net 30" (2% discount if paid within 10 days)
        if (preg_match('/(\d+)\/(\d+)\s+Net\s+\d+/i', $terms ?? '', $matches)) {
            $discountPercent = (float) $matches[1] / 100;
            $discountDays = (int) $matches[2];

            $daysSinceBill = $bill->getBillDate()->diff($paymentDate)->days;

            if ($daysSinceBill <= $discountDays) {
                return $bill->total * $discountPercent;
            }
        }

        return 0.0;
    }

    /**
     * Void a payment
     */
    public function voidPayment(BillPayment $payment, string $reason): void
    {
        if ($payment->status === 'Void') {
            throw new \RuntimeException('Payment is already voided');
        }

        // Reverse all applications
        foreach ($payment->applications as $application) {
            $bill = $application->vendorBill;

            // Reverse the payment on the bill
            $bill->amountPaid -= $application->amountApplied;
            $bill->discountAmount -= $application->discountApplied;
            $bill->amountDue = $bill->total - $bill->discountAmount - $bill->amountPaid;

            // Update bill status
            if ($bill->amountDue > 0.001) {
                $bill->status = $bill->amountPaid > 0 ? 'Partially Paid' : 'Open';
            }

            $bill->updatedAt = new \DateTime();
        }

        $payment->status = 'Void';
        $payment->updatedAt = new \DateTime();

        $this->entityManager->flush();
    }
}
