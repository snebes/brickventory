<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InventoryAdjustment;
use App\Entity\InventoryAdjustmentLine;
use App\Entity\Item;
use App\Event\InventoryAdjustedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventory-adjustments', name: 'api_inventory_adjustments_')]
class InventoryAdjustmentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $adjustments = $this->entityManager->getRepository(InventoryAdjustment::class)->findAll();
        
        $data = array_map(function (InventoryAdjustment $adjustment) {
            return [
                'id' => $adjustment->id,
                'uuid' => $adjustment->uuid,
                'adjustmentNumber' => $adjustment->adjustmentNumber,
                'adjustmentDate' => $adjustment->adjustmentDate->format('Y-m-d H:i:s'),
                'reason' => $adjustment->reason,
                'memo' => $adjustment->memo,
                'status' => $adjustment->status,
                'lines' => array_map(function (InventoryAdjustmentLine $line) {
                    return [
                        'id' => $line->id,
                        'item' => [
                            'id' => $line->item->id,
                            'itemId' => $line->item->itemId,
                            'itemName' => $line->item->itemName,
                        ],
                        'quantityChange' => $line->quantityChange,
                        'notes' => $line->notes,
                    ];
                }, $adjustment->lines->toArray()),
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
            'reason' => $adjustment->reason,
            'memo' => $adjustment->memo,
            'status' => $adjustment->status,
            'lines' => array_map(function (InventoryAdjustmentLine $line) {
                return [
                    'id' => $line->id,
                    'item' => [
                        'id' => $line->item->id,
                        'itemId' => $line->item->itemId,
                        'itemName' => $line->item->itemName,
                    ],
                    'quantityChange' => $line->quantityChange,
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

        // Validate required fields
        if (empty($data['reason'])) {
            return $this->json(['error' => 'Reason is required'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['lines']) || !is_array($data['lines'])) {
            return $this->json(['error' => 'At least one adjustment line is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->entityManager->beginTransaction();

            // Create inventory adjustment
            $adjustment = new InventoryAdjustment();
            $adjustment->adjustmentNumber = $data['adjustmentNumber'] ?? 'ADJ-' . date('YmdHis');
            $adjustment->adjustmentDate = isset($data['adjustmentDate']) ? new \DateTime($data['adjustmentDate']) : new \DateTime();
            $adjustment->reason = $data['reason'];
            $adjustment->memo = $data['memo'] ?? null;
            $adjustment->status = $data['status'] ?? 'approved';

            $this->entityManager->persist($adjustment);
            $this->entityManager->flush(); // Flush to get the ID for event reference

            // Process adjustment lines
            foreach ($data['lines'] as $lineData) {
                if (!isset($lineData['itemId']) || !isset($lineData['quantityChange'])) {
                    $this->entityManager->rollback();
                    return $this->json(['error' => 'Each line must have itemId and quantityChange'], Response::HTTP_BAD_REQUEST);
                }

                // Validate quantity is numeric
                if (!is_numeric($lineData['quantityChange'])) {
                    $this->entityManager->rollback();
                    return $this->json(['error' => 'Quantity change must be numeric'], Response::HTTP_BAD_REQUEST);
                }

                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                
                if (!$item) {
                    $this->entityManager->rollback();
                    return $this->json(['error' => 'Item not found: ' . $lineData['itemId']], Response::HTTP_NOT_FOUND);
                }

                $quantityChange = (int)$lineData['quantityChange'];

                // Skip lines with zero quantity
                if ($quantityChange === 0) {
                    continue;
                }

                // Create adjustment line
                $adjustmentLine = new InventoryAdjustmentLine();
                $adjustmentLine->inventoryAdjustment = $adjustment;
                $adjustmentLine->item = $item;
                $adjustmentLine->quantityChange = $quantityChange;
                $adjustmentLine->notes = $lineData['notes'] ?? null;
                
                $adjustment->lines->add($adjustmentLine);

                // Dispatch event for inventory update - this immediately updates the item quantity
                $event = new InventoryAdjustedEvent($item, $quantityChange, $adjustment);
                $this->eventDispatcher->dispatch($event);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $this->json([
                'id' => $adjustment->id,
                'uuid' => $adjustment->uuid,
                'adjustmentNumber' => $adjustment->adjustmentNumber,
                'message' => 'Inventory adjustment created and applied successfully'
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $adjustment = $this->entityManager->getRepository(InventoryAdjustment::class)->find($id);
        
        if (!$adjustment) {
            return $this->json(['error' => 'Inventory adjustment not found'], Response::HTTP_NOT_FOUND);
        }

        // Note: Deleting an adjustment does not reverse the inventory changes
        // This is by design as per event sourcing pattern - the events are the source of truth
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
