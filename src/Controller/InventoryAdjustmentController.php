<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InventoryAdjustment;
use App\Entity\InventoryAdjustmentLine;
use App\Service\InventoryAdjustmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventory-adjustments', name: 'api_inventory_adjustments_')]
class InventoryAdjustmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryAdjustmentService $adjustmentService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->entityManager->getRepository(InventoryAdjustment::class)->createQueryBuilder('a');
        
        // Filter by status
        if ($request->query->has('status')) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $request->query->get('status'));
        }
        
        // Filter by adjustment type
        if ($request->query->has('type')) {
            $qb->andWhere('a.adjustmentType = :type')
               ->setParameter('type', $request->query->get('type'));
        }
        
        // Order by date descending
        $qb->orderBy('a.adjustmentDate', 'DESC');
        
        $adjustments = $qb->getQuery()->getResult();
        
        $data = array_map(function (InventoryAdjustment $adjustment) {
            return [
                'id' => $adjustment->id,
                'uuid' => $adjustment->uuid,
                'adjustmentNumber' => $adjustment->adjustmentNumber,
                'adjustmentDate' => $adjustment->adjustmentDate->format('Y-m-d H:i:s'),
                'adjustmentType' => $adjustment->adjustmentType,
                'reason' => $adjustment->reason,
                'memo' => $adjustment->memo,
                'status' => $adjustment->status,
                'location' => [
                    'id' => $adjustment->location->id,
                    'locationCode' => $adjustment->location->locationCode,
                    'locationName' => $adjustment->location->locationName,
                ],
                'totalQuantityChange' => $adjustment->totalQuantityChange,
                'totalValueChange' => $adjustment->totalValueChange,
                'approvalRequired' => $adjustment->approvalRequired,
                'approvedBy' => $adjustment->approvedBy,
                'approvedAt' => $adjustment->approvedAt?->format('Y-m-d H:i:s'),
                'postedBy' => $adjustment->postedBy,
                'postedAt' => $adjustment->postedAt?->format('Y-m-d H:i:s'),
                'lineCount' => $adjustment->lines->count(),
            ];
        }, $adjustments);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $adjustment = $this->entityManager->getRepository(InventoryAdjustment::class)->find($id);
        
        if (!$adjustment) {
            return $this->json(['error' => 'Inventory adjustment not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $adjustment->id,
            'uuid' => $adjustment->uuid,
            'adjustmentNumber' => $adjustment->adjustmentNumber,
            'adjustmentDate' => $adjustment->adjustmentDate->format('Y-m-d H:i:s'),
            'adjustmentType' => $adjustment->adjustmentType,
            'reason' => $adjustment->reason,
            'memo' => $adjustment->memo,
            'status' => $adjustment->status,
            'postingPeriod' => $adjustment->postingPeriod,
            'location' => [
                'id' => $adjustment->location->id,
                'locationCode' => $adjustment->location->locationCode,
                'locationName' => $adjustment->location->locationName,
            ],
            'totalQuantityChange' => $adjustment->totalQuantityChange,
            'totalValueChange' => $adjustment->totalValueChange,
            'approvalRequired' => $adjustment->approvalRequired,
            'approvedBy' => $adjustment->approvedBy,
            'approvedAt' => $adjustment->approvedAt?->format('Y-m-d H:i:s'),
            'postedBy' => $adjustment->postedBy,
            'postedAt' => $adjustment->postedAt?->format('Y-m-d H:i:s'),
            'referenceNumber' => $adjustment->referenceNumber,
            'countDate' => $adjustment->countDate?->format('Y-m-d H:i:s'),
            'lines' => array_map(function (InventoryAdjustmentLine $line) {
                return [
                    'id' => $line->id,
                    'uuid' => $line->uuid,
                    'item' => [
                        'id' => $line->item->id,
                        'itemId' => $line->item->itemId,
                        'itemName' => $line->item->itemName,
                    ],
                    'adjustmentType' => $line->adjustmentType,
                    'quantityChange' => $line->quantityChange,
                    'quantityBefore' => $line->quantityBefore,
                    'quantityAfter' => $line->quantityAfter,
                    'currentUnitCost' => $line->currentUnitCost,
                    'adjustmentUnitCost' => $line->adjustmentUnitCost,
                    'newUnitCost' => $line->newUnitCost,
                    'totalCostImpact' => $line->totalCostImpact,
                    'binLocation' => $line->binLocation,
                    'lotNumber' => $line->lotNumber,
                    'serialNumber' => $line->serialNumber,
                    'layersAffected' => $line->layersAffected,
                    'notes' => $line->notes,
                ];
            }, $adjustment->lines->toArray()),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields - location is required (NetSuite ERP pattern)
        if (empty($data['locationId'])) {
            return $this->json(['error' => 'Location is required for inventory adjustments'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['reason'])) {
            return $this->json(['error' => 'Reason is required'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['lines']) || !is_array($data['lines'])) {
            return $this->json(['error' => 'At least one adjustment line is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $adjustment = $this->adjustmentService->createQuantityAdjustment(
                (int) $data['locationId'],
                $data['lines'],
                $data['reason'],
                $data['memo'] ?? null,
                false // Don't auto-post
            );

            return $this->json([
                'id' => $adjustment->id,
                'uuid' => $adjustment->uuid,
                'adjustmentNumber' => $adjustment->adjustmentNumber,
                'status' => $adjustment->status,
                'location' => [
                    'id' => $adjustment->location->id,
                    'locationCode' => $adjustment->location->locationCode,
                    'locationName' => $adjustment->location->locationName,
                ],
                'message' => 'Inventory adjustment created successfully'
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create adjustment'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/post', name: 'post', methods: ['POST'])]
    public function post(int $id): JsonResponse
    {
        try {
            $this->adjustmentService->postAdjustment($id);
            
            return $this->json([
                'message' => 'Adjustment posted successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to post adjustment'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/reverse', name: 'reverse', methods: ['POST'])]
    public function reverse(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['reason'])) {
            return $this->json(['error' => 'Reason is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $reversingAdjustment = $this->adjustmentService->reverseAdjustment($id, $data['reason']);
            
            return $this->json([
                'id' => $reversingAdjustment->id,
                'adjustmentNumber' => $reversingAdjustment->adjustmentNumber,
                'message' => 'Adjustment reversed successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to reverse adjustment'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $approverId = $data['approverId'] ?? 'system'; // TODO: Get from security context
        
        try {
            $this->adjustmentService->approveAdjustment($id, $approverId);
            
            return $this->json([
                'message' => 'Adjustment approved successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to approve adjustment'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/submit-for-approval', name: 'submit_for_approval', methods: ['POST'])]
    public function submitForApproval(int $id): JsonResponse
    {
        $adjustment = $this->entityManager->getRepository(InventoryAdjustment::class)->find($id);
        
        if (!$adjustment) {
            return $this->json(['error' => 'Inventory adjustment not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$adjustment->isDraft()) {
            return $this->json(
                ['error' => 'Only draft adjustments can be submitted for approval. Current status: ' . $adjustment->status],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Change status to pending approval
        $adjustment->status = InventoryAdjustment::STATUS_PENDING_APPROVAL;
        $adjustment->approvalRequired = true;

        $this->entityManager->persist($adjustment);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Adjustment submitted for approval successfully'
        ]);
    }

    #[Route('/pending-approval', name: 'pending_approval', methods: ['GET'])]
    public function pendingApproval(): JsonResponse
    {
        $adjustments = $this->entityManager->getRepository(InventoryAdjustment::class)
            ->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', InventoryAdjustment::STATUS_PENDING_APPROVAL)
            ->orderBy('a.adjustmentDate', 'DESC')
            ->getQuery()
            ->getResult();
        
        $data = array_map(function (InventoryAdjustment $adjustment) {
            return [
                'id' => $adjustment->id,
                'uuid' => $adjustment->uuid,
                'adjustmentNumber' => $adjustment->adjustmentNumber,
                'adjustmentDate' => $adjustment->adjustmentDate->format('Y-m-d H:i:s'),
                'adjustmentType' => $adjustment->adjustmentType,
                'reason' => $adjustment->reason,
                'location' => [
                    'id' => $adjustment->location->id,
                    'locationCode' => $adjustment->location->locationCode,
                    'locationName' => $adjustment->location->locationName,
                ],
                'totalQuantityChange' => $adjustment->totalQuantityChange,
                'totalValueChange' => $adjustment->totalValueChange,
                'lineCount' => $adjustment->lines->count(),
            ];
        }, $adjustments);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $adjustment = $this->entityManager->getRepository(InventoryAdjustment::class)->find($id);
        
        if (!$adjustment) {
            return $this->json(['error' => 'Inventory adjustment not found'], Response::HTTP_NOT_FOUND);
        }

        // Only draft adjustments can be edited
        if (!$adjustment->canBeEdited()) {
            return $this->json(
                ['error' => 'Only draft adjustments can be edited. Current status: ' . $adjustment->status],
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->entityManager->beginTransaction();

            // Update location if provided
            if (isset($data['locationId'])) {
                $location = $this->entityManager->getRepository(\App\Entity\Location::class)->find($data['locationId']);
                if (!$location) {
                    throw new \InvalidArgumentException("Location with ID {$data['locationId']} not found");
                }
                if (!$location->active) {
                    throw new \InvalidArgumentException("Location is inactive and cannot be used for adjustments");
                }
                $adjustment->location = $location;
            }

            // Update basic fields
            if (isset($data['adjustmentDate'])) {
                $adjustment->adjustmentDate = new \DateTime($data['adjustmentDate']);
            }
            if (isset($data['reason'])) {
                $adjustment->reason = $data['reason'];
            }
            if (isset($data['memo'])) {
                $adjustment->memo = $data['memo'];
            }

            // Update lines if provided
            if (isset($data['lines']) && is_array($data['lines'])) {
                // Remove existing lines
                foreach ($adjustment->lines as $line) {
                    $this->entityManager->remove($line);
                }
                $adjustment->lines->clear();

                // Add new lines
                $totalQuantityChange = 0.0;
                foreach ($data['lines'] as $lineData) {
                    $item = $this->entityManager->getRepository(\App\Entity\Item::class)->find($lineData['itemId']);
                    
                    if (!$item) {
                        throw new \InvalidArgumentException("Item with ID {$lineData['itemId']} not found");
                    }
                    
                    $line = new InventoryAdjustmentLine();
                    $line->inventoryAdjustment = $adjustment;
                    $line->item = $item;
                    $line->adjustmentType = InventoryAdjustmentLine::TYPE_QUANTITY;
                    $line->quantityChange = $lineData['quantityChange'];
                    $line->quantityBefore = (float)$item->quantityOnHand;
                    $line->quantityAfter = $line->quantityBefore + $lineData['quantityChange'];
                    $line->notes = $lineData['notes'] ?? null;
                    
                    if (isset($lineData['unitCost'])) {
                        $line->currentUnitCost = $lineData['unitCost'];
                        $line->totalCostImpact = $lineData['quantityChange'] * $lineData['unitCost'];
                    }
                    
                    $adjustment->lines->add($line);
                    $this->entityManager->persist($line);
                    $totalQuantityChange += $lineData['quantityChange'];
                }
                
                $adjustment->totalQuantityChange = $totalQuantityChange;
            }

            $this->entityManager->persist($adjustment);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $this->json([
                'id' => $adjustment->id,
                'uuid' => $adjustment->uuid,
                'adjustmentNumber' => $adjustment->adjustmentNumber,
                'status' => $adjustment->status,
                'message' => 'Inventory adjustment updated successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->entityManager->rollback();
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return $this->json(['error' => 'Failed to update adjustment: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $adjustment = $this->entityManager->getRepository(InventoryAdjustment::class)->find($id);
        
        if (!$adjustment) {
            return $this->json(['error' => 'Inventory adjustment not found'], Response::HTTP_NOT_FOUND);
        }

        if ($adjustment->isPosted()) {
            return $this->json(
                ['error' => 'Cannot delete posted adjustment. Use reverse instead.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->entityManager->remove($adjustment);
        $this->entityManager->flush();

        return $this->json(['message' => 'Inventory adjustment deleted successfully']);
    }

    #[Route('/reasons', name: 'reasons', methods: ['GET'])]
    public function reasons(): JsonResponse
    {
        // Common adjustment reasons modeled after NetSuite
        $reasons = [
            ['id' => 'physical_count', 'name' => 'Physical Count'],
            ['id' => 'damaged', 'name' => 'Damaged Goods'],
            ['id' => 'lost', 'name' => 'Lost/Missing'],
            ['id' => 'found', 'name' => 'Found/Recovered'],
            ['id' => 'correction', 'name' => 'Correction'],
            ['id' => 'transfer_in', 'name' => 'Transfer In'],
            ['id' => 'transfer_out', 'name' => 'Transfer Out'],
            ['id' => 'production', 'name' => 'Production Output'],
            ['id' => 'scrap', 'name' => 'Scrap/Waste'],
            ['id' => 'sample', 'name' => 'Sample/Demo'],
            ['id' => 'other', 'name' => 'Other'],
        ];

        return $this->json($reasons);
    }
}

