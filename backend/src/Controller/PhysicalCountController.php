<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PhysicalCount;
use App\Entity\PhysicalCountLine;
use App\Service\PhysicalCountService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/physical-counts', name: 'api_physical_counts_')]
class PhysicalCountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PhysicalCountService $countService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->entityManager->getRepository(PhysicalCount::class)->createQueryBuilder('pc');
        
        // Filter by status
        if ($request->query->has('status')) {
            $qb->andWhere('pc.status = :status')
               ->setParameter('status', $request->query->get('status'));
        }
        
        // Filter by count type
        if ($request->query->has('type')) {
            $qb->andWhere('pc.countType = :type')
               ->setParameter('type', $request->query->get('type'));
        }
        
        // Order by date descending
        $qb->orderBy('pc.countDate', 'DESC');
        
        $counts = $qb->getQuery()->getResult();
        
        $data = array_map(function (PhysicalCount $count) {
            $totalLines = $count->lines->count();
            $countedLines = 0;
            foreach ($count->lines as $line) {
                if ($line->isCounted()) {
                    $countedLines++;
                }
            }
            
            return [
                'id' => $count->id,
                'uuid' => $count->uuid,
                'countNumber' => $count->countNumber,
                'countType' => $count->countType,
                'countDate' => $count->countDate->format('Y-m-d H:i:s'),
                'locationId' => $count->locationId,
                'status' => $count->status,
                'scheduledDate' => $count->scheduledDate?->format('Y-m-d H:i:s'),
                'freezeTransactions' => $count->freezeTransactions,
                'completedAt' => $count->completedAt?->format('Y-m-d H:i:s'),
                'totalLines' => $totalLines,
                'countedLines' => $countedLines,
                'hasVariances' => $count->hasVariances(),
            ];
        }, $counts);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $count = $this->entityManager->getRepository(PhysicalCount::class)->find($id);
        
        if (!$count) {
            return $this->json(['error' => 'Physical count not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $count->id,
            'uuid' => $count->uuid,
            'countNumber' => $count->countNumber,
            'countType' => $count->countType,
            'countDate' => $count->countDate->format('Y-m-d H:i:s'),
            'locationId' => $count->locationId,
            'status' => $count->status,
            'scheduledDate' => $count->scheduledDate?->format('Y-m-d H:i:s'),
            'freezeTransactions' => $count->freezeTransactions,
            'completedAt' => $count->completedAt?->format('Y-m-d H:i:s'),
            'notes' => $count->notes,
            'lines' => array_map(function (PhysicalCountLine $line) {
                return [
                    'id' => $line->id,
                    'uuid' => $line->uuid,
                    'item' => [
                        'id' => $line->item->id,
                        'itemId' => $line->item->itemId,
                        'itemName' => $line->item->itemName,
                    ],
                    'locationId' => $line->locationId,
                    'binLocation' => $line->binLocation,
                    'lotNumber' => $line->lotNumber,
                    'serialNumber' => $line->serialNumber,
                    'systemQuantity' => $line->systemQuantity,
                    'countedQuantity' => $line->countedQuantity,
                    'varianceQuantity' => $line->varianceQuantity,
                    'variancePercent' => $line->variancePercent,
                    'varianceValue' => $line->varianceValue,
                    'countedBy' => $line->countedBy,
                    'countedAt' => $line->countedAt?->format('Y-m-d H:i:s'),
                    'verifiedBy' => $line->verifiedBy,
                    'verifiedAt' => $line->verifiedAt?->format('Y-m-d H:i:s'),
                    'recountRequired' => $line->recountRequired,
                    'recountQuantity' => $line->recountQuantity,
                    'adjustmentLineId' => $line->adjustmentLine?->id,
                    'notes' => $line->notes,
                ];
            }, $count->lines->toArray()),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        if (empty($data['countType'])) {
            return $this->json(['error' => 'Count type is required'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['itemIds']) || !is_array($data['itemIds'])) {
            return $this->json(['error' => 'At least one item is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $count = $this->countService->createPhysicalCount(
                $data['locationId'] ?? null,
                $data['countType'],
                $data['itemIds']
            );

            return $this->json([
                'id' => $count->id,
                'uuid' => $count->uuid,
                'countNumber' => $count->countNumber,
                'status' => $count->status,
                'message' => 'Physical count created successfully'
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create physical count'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    public function complete(int $id): JsonResponse
    {
        $count = $this->entityManager->getRepository(PhysicalCount::class)->find($id);
        
        if (!$count) {
            return $this->json(['error' => 'Physical count not found'], Response::HTTP_NOT_FOUND);
        }

        $count->status = PhysicalCount::STATUS_COMPLETED;
        $count->completedAt = new \DateTime();
        
        $this->entityManager->persist($count);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Physical count marked as complete'
        ]);
    }

    #[Route('/{id}/create-adjustment', name: 'create_adjustment', methods: ['POST'])]
    public function createAdjustment(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $autoPost = $data['autoPost'] ?? false;

        try {
            $adjustment = $this->countService->createAdjustmentFromCount($id, $autoPost);
            
            if (!$adjustment) {
                return $this->json([
                    'message' => 'No variances found. No adjustment created.'
                ]);
            }

            return $this->json([
                'adjustmentId' => $adjustment->id,
                'adjustmentNumber' => $adjustment->adjustmentNumber,
                'message' => 'Adjustment created from physical count'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create adjustment'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/lines/{lineId}/count', name: 'record_count', methods: ['PUT'])]
    public function recordCount(int $id, int $lineId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['countedQuantity'])) {
            return $this->json(['error' => 'countedQuantity is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->countService->recordCountResult(
                $lineId,
                (float)$data['countedQuantity'],
                $data['countedBy'] ?? 'system'
            );

            return $this->json([
                'message' => 'Count recorded successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to record count'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/lines/{lineId}/recount', name: 'recount', methods: ['POST'])]
    public function recount(int $id, int $lineId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['recountQuantity'])) {
            return $this->json(['error' => 'recountQuantity is required'], Response::HTTP_BAD_REQUEST);
        }

        $line = $this->entityManager->getRepository(PhysicalCountLine::class)->find($lineId);
        
        if (!$line) {
            return $this->json(['error' => 'Count line not found'], Response::HTTP_NOT_FOUND);
        }

        $line->recountQuantity = (float)$data['recountQuantity'];
        $line->recountRequired = false;
        $line->verifiedBy = $data['verifiedBy'] ?? 'system';
        $line->verifiedAt = new \DateTime();
        
        // Update the counted quantity to the recount quantity
        $line->countedQuantity = $line->recountQuantity;
        $line->calculateVariance();
        
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Recount recorded successfully'
        ]);
    }

    #[Route('/cycle-counts/due', name: 'cycle_counts_due', methods: ['GET'])]
    public function cycleCountsDue(Request $request): JsonResponse
    {
        $locationId = $request->query->get('locationId') ? (int)$request->query->get('locationId') : null;
        
        try {
            $items = $this->countService->getItemsDueForCycleCount($locationId);
            
            return $this->json([
                'items' => $items,
                'message' => 'Cycle count scheduling not yet fully implemented'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to get cycle count items'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
