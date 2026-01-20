<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BillPayment;
use App\Entity\Vendor;
use App\Service\BillPaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/bill-payments', name: 'api_bill_payments_')]
class BillPaymentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BillPaymentService $billPaymentService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $vendorId = $request->query->get('vendorId');
        
        $qb = $this->entityManager
            ->getRepository(BillPayment::class)
            ->createQueryBuilder('bp')
            ->orderBy('bp.paymentDate', 'DESC');

        if ($vendorId) {
            $qb->andWhere('bp.vendor = :vendorId')
               ->setParameter('vendorId', $vendorId);
        }

        $payments = $qb->getQuery()->getResult();

        return $this->json([
            'payments' => array_map(fn($p) => $this->serializeBillPayment($p, false), $payments)
        ]);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $payment = $this->entityManager->getRepository(BillPayment::class)->find($id);
        
        if (!$payment) {
            return $this->json(['error' => 'Bill payment not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeBillPayment($payment, true));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['vendorId']) || !isset($data['billApplications'])) {
            return $this->json(['error' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $vendor = $this->entityManager->getRepository(Vendor::class)->find($data['vendorId']);
            
            if (!$vendor) {
                return $this->json(['error' => 'Vendor not found'], Response::HTTP_NOT_FOUND);
            }

            $payment = $this->billPaymentService->createPayment(
                $vendor,
                $data['billApplications'],
                $data['paymentMethod'] ?? 'Check',
                isset($data['paymentDate']) ? new \DateTime($data['paymentDate']) : null,
                $data['checkNumber'] ?? null
            );

            return $this->json([
                'id' => $payment->id,
                'message' => 'Payment created successfully',
                'payment' => $this->serializeBillPayment($payment, true)
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/void', name: 'void', methods: ['POST'])]
    public function void(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        try {
            $payment = $this->entityManager->getRepository(BillPayment::class)->find($id);
            
            if (!$payment) {
                return $this->json(['error' => 'Bill payment not found'], Response::HTTP_NOT_FOUND);
            }

            $reason = $data['reason'] ?? 'Voided';
            
            $this->billPaymentService->voidPayment($payment, $reason);

            return $this->json([
                'id' => $id,
                'message' => 'Payment voided successfully',
                'status' => $payment->status
            ]);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function serializeBillPayment(BillPayment $payment, bool $includeApplications = true): array
    {
        $data = [
            'id' => $payment->id,
            'paymentNumber' => $payment->paymentNumber,
            'vendor' => [
                'id' => $payment->vendor->id,
                'vendorCode' => $payment->vendor->vendorCode,
                'vendorName' => $payment->vendor->vendorName,
            ],
            'paymentDate' => $payment->paymentDate->format('Y-m-d'),
            'paymentMethod' => $payment->paymentMethod,
            'checkNumber' => $payment->checkNumber,
            'totalAmount' => $payment->totalAmount,
            'discountTaken' => $payment->discountTaken,
            'status' => $payment->status,
        ];

        if ($includeApplications) {
            $data['applications'] = array_map(function ($app) {
                return [
                    'id' => $app->id,
                    'vendorBill' => [
                        'id' => $app->vendorBill->id,
                        'billNumber' => $app->vendorBill->billNumber,
                    ],
                    'amountApplied' => $app->amountApplied,
                    'discountApplied' => $app->discountApplied,
                    'appliedAt' => $app->appliedAt->format('Y-m-d H:i:s'),
                ];
            }, $payment->applications->toArray());
        }

        return $data;
    }
}
