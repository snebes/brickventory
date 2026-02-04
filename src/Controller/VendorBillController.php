<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ItemReceipt;
use App\Entity\VendorBill;
use App\Service\VendorBillService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/vendor-bills', name: 'api_vendor_bills_')]
class VendorBillController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VendorBillService $vendorBillService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $vendorId = $request->query->get('vendorId');
        $status = $request->query->get('status');

        $qb = $this->entityManager
            ->getRepository(VendorBill::class)
            ->createQueryBuilder('vb')
            ->orderBy('vb.transactionDate', 'DESC');

        if ($vendorId) {
            $qb->andWhere('vb.vendor = :vendorId')
               ->setParameter('vendorId', $vendorId);
        }

        if ($status) {
            $qb->andWhere('vb.status = :status')
               ->setParameter('status', $status);
        }

        $bills = $qb->getQuery()->getResult();

        return $this->json([
            'bills' => array_map(fn($b) => $this->serializeVendorBill($b, false), $bills)
        ]);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $bill = $this->entityManager->getRepository(VendorBill::class)->find($id);

        if (!$bill) {
            return $this->json(['error' => 'Vendor bill not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeVendorBill($bill, true));
    }

    #[Route('/from-receipt/{receiptId}', name: 'create_from_receipt', methods: ['POST'])]
    public function createFromReceipt(int $receiptId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $receipt = $this->entityManager->getRepository(ItemReceipt::class)->find($receiptId);

            if (!$receipt) {
                return $this->json(['error' => 'Item receipt not found'], Response::HTTP_NOT_FOUND);
            }

            $bill = $this->vendorBillService->createBillFromReceipt(
                $receipt,
                $data['vendorInvoiceNumber'] ?? null,
                isset($data['vendorInvoiceDate']) ? new \DateTime($data['vendorInvoiceDate']) : null
            );

            return $this->json([
                'id' => $bill->id,
                'message' => 'Vendor bill created from receipt',
                'bill' => $this->serializeVendorBill($bill, true)
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/match', name: 'three_way_match', methods: ['POST'])]
    public function performThreeWayMatch(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $bill = $this->entityManager->getRepository(VendorBill::class)->find($id);

            if (!$bill) {
                return $this->json(['error' => 'Vendor bill not found'], Response::HTTP_NOT_FOUND);
            }

            $qtyTolerance = $data['qtyTolerance'] ?? 5.0;
            $priceTolerance = $data['priceTolerance'] ?? 5.0;

            $matchResults = $this->vendorBillService->performThreeWayMatch(
                $bill,
                $qtyTolerance,
                $priceTolerance
            );

            return $this->json($matchResults);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $bill = $this->entityManager->getRepository(VendorBill::class)->find($id);

            if (!$bill) {
                return $this->json(['error' => 'Vendor bill not found'], Response::HTTP_NOT_FOUND);
            }

            $approverId = $data['approverId'] ?? null;

            if (!$approverId) {
                return $this->json(['error' => 'approverId is required'], Response::HTTP_BAD_REQUEST);
            }

            $this->vendorBillService->approveBill($bill, $approverId);

            return $this->json([
                'id' => $id,
                'message' => 'Vendor bill approved successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function serializeVendorBill(VendorBill $bill, bool $includeLines = true): array
    {
        $data = [
            'id' => $bill->id,
            'billNumber' => $bill->billNumber,
            'vendor' => [
                'id' => $bill->vendor->id,
                'vendorCode' => $bill->vendor->vendorCode,
                'vendorName' => $bill->vendor->vendorName,
            ],
            'vendorInvoiceNumber' => $bill->vendorInvoiceNumber,
            'vendorInvoiceDate' => $bill->vendorInvoiceDate?->format('Y-m-d'),
            'billDate' => $bill->getBillDate()->format('Y-m-d'),
            'dueDate' => $bill->dueDate->format('Y-m-d'),
            'status' => $bill->status,
            'subtotal' => $bill->subtotal,
            'taxTotal' => $bill->taxTotal,
            'freightAmount' => $bill->freightAmount,
            'total' => $bill->total,
            'amountPaid' => $bill->amountPaid,
            'amountDue' => $bill->amountDue,
        ];

        if ($includeLines) {
            $data['lines'] = array_map(function ($line) {
                return [
                    'id' => $line->id,
                    'lineType' => $line->lineType,
                    'item' => $line->item ? [
                        'id' => $line->item->id,
                        'itemName' => $line->item->itemName,
                    ] : null,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unitCost' => $line->unitCost,
                    'amount' => $line->amount,
                    'varianceAmount' => $line->varianceAmount,
                ];
            }, $bill->lines->toArray());
        }

        return $data;
    }
}
