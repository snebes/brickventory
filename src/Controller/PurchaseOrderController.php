<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Event\PurchaseOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/purchase-orders', name: 'api_purchase_orders_')]
class PurchaseOrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $purchaseOrders = $this->entityManager->getRepository(PurchaseOrder::class)->findAll();
        
        $data = array_map(function (PurchaseOrder $po) {
            return [
                'id' => $po->id,
                'orderNumber' => $po->orderNumber,
                'orderDate' => $po->orderDate->format('Y-m-d H:i:s'),
                'status' => $po->status,
                'reference' => $po->reference,
                'notes' => $po->notes,
                'lines' => array_map(function (PurchaseOrderLine $line) {
                    return [
                        'id' => $line->id,
                        'item' => [
                            'id' => $line->item->id,
                            'itemId' => $line->item->itemId,
                            'itemName' => $line->item->itemName,
                        ],
                        'quantityOrdered' => $line->quantityOrdered,
                        'quantityReceived' => $line->quantityReceived,
                        'rate' => $line->rate,
                    ];
                }, $po->lines->toArray()),
            ];
        }, $purchaseOrders);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($id);
        
        if (!$po) {
            return $this->json(['error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $po->id,
            'orderNumber' => $po->orderNumber,
            'orderDate' => $po->orderDate->format('Y-m-d H:i:s'),
            'status' => $po->status,
            'reference' => $po->reference,
            'notes' => $po->notes,
            'lines' => array_map(function (PurchaseOrderLine $line) {
                return [
                    'id' => $line->id,
                    'item' => [
                        'id' => $line->item->id,
                        'itemId' => $line->item->itemId,
                        'itemName' => $line->item->itemName,
                    ],
                    'quantityOrdered' => $line->quantityOrdered,
                    'quantityReceived' => $line->quantityReceived,
                    'rate' => $line->rate,
                ];
            }, $po->lines->toArray()),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $po = new PurchaseOrder();
        $po->orderNumber = $data['orderNumber'] ?? 'PO-' . date('YmdHis');
        $po->orderDate = isset($data['orderDate']) ? new \DateTime($data['orderDate']) : new \DateTime();
        $po->status = $data['status'] ?? 'pending';
        $po->reference = $data['reference'] ?? null;
        $po->notes = $data['notes'] ?? null;

        if (!empty($data['lines'])) {
            foreach ($data['lines'] as $lineData) {
                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                if (!$item) {
                    return $this->json(['error' => 'Item not found: ' . $lineData['itemId']], Response::HTTP_BAD_REQUEST);
                }

                $line = new PurchaseOrderLine();
                $line->purchaseOrder = $po;
                $line->item = $item;
                $line->quantityOrdered = $lineData['quantityOrdered'];
                $line->rate = $lineData['rate'] ?? 0.0;
                
                $po->lines->add($line);
            }
        }

        $this->entityManager->persist($po);
        $this->entityManager->flush();

        // Dispatch event to update inventory
        $this->eventDispatcher->dispatch(new PurchaseOrderCreatedEvent($po));

        return $this->json([
            'id' => $po->id,
            'orderNumber' => $po->orderNumber,
            'message' => 'Purchase order created successfully'
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($id);
        
        if (!$po) {
            return $this->json(['error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Update basic fields (orderNumber is intentionally not updatable to preserve uniqueness)
        if (isset($data['orderDate'])) {
            $po->orderDate = new \DateTime($data['orderDate']);
        }
        if (isset($data['status'])) {
            $po->status = $data['status'];
        }
        if (isset($data['reference'])) {
            $po->reference = $data['reference'];
        }
        if (isset($data['notes'])) {
            $po->notes = $data['notes'];
        }

        // Update lines if provided
        if (isset($data['lines'])) {
            // Remove existing lines
            foreach ($po->lines as $line) {
                $this->entityManager->remove($line);
            }
            $po->lines->clear();

            // Add new lines
            foreach ($data['lines'] as $lineData) {
                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                if (!$item) {
                    return $this->json(['error' => 'Item not found: ' . $lineData['itemId']], Response::HTTP_BAD_REQUEST);
                }

                $line = new PurchaseOrderLine();
                $line->purchaseOrder = $po;
                $line->item = $item;
                $line->quantityOrdered = $lineData['quantityOrdered'];
                $line->rate = $lineData['rate'] ?? 0.0;
                
                $po->lines->add($line);
                $this->entityManager->persist($line);
            }
        }

        $this->entityManager->flush();

        // Re-dispatch event to update inventory
        $this->eventDispatcher->dispatch(new PurchaseOrderCreatedEvent($po));

        return $this->json([
            'id' => $po->id,
            'message' => 'Purchase order updated successfully'
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($id);
        
        if (!$po) {
            return $this->json(['error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($po);
        $this->entityManager->flush();

        return $this->json(['message' => 'Purchase order deleted successfully']);
    }
}
